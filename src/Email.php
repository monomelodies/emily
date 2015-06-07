<?php

/**
 * @package Emily
 */

namespace Emily;

use Adapter_Access;
use monolyth\utils\HTML_Helper;
use ErrorException;
use Closure;
use Project;
use Twig_Autoloader;
use Twig_Environment;
use Swift_Message;
use Swift_Attachment;

class Email extends Swift_Message
{
    use Url_Helper;
    use HTML_Helper;
    use Adapter_Access;
    use Static_Helper;
    use Singleton;

    const TYPE_HTML = 1;
    const TYPE_PLAIN = 2;

    private $headers = [];
    private $variables = [];
    private $content = [];
    protected $twig;
    protected $subject = null;
    protected $test = false;

    public function __construct()
    {
        try {
            $vars = call_user_func(function() {
                include 'output/css/variables.php';
                return get_defined_vars();
            });
            foreach ($vars as $key => $var) {
                if (is_callable($var)) {
                    unset($vars[$key]);
                }
            }
            $this->setVariables($vars);
        } catch (ErrorException $e) {
        }
        $loader = new Twig_Email;
        $this->twig = new Twig_Environment($loader, [
            'cache' => '/tmp',
            'debug' => true,
        ]);
        $this->parser = new Translate_Parser;
        $this->setEnvironment(Project::instance());
    }

    /**
     * "Subject" can contain Twig variables, so we don't actually set it
     * until we reach the parsing stage.
     * {{{
     */

    /**
     * Proxy for setting the subject.
     *
     * @param string $subject The subject.
     * @return Emily\Email $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Proxy for getting the subject.
     *
     * @return string|null The subject, or null if not set yet.
     */
    public function getSubject()
    {
        return $this->subject;
    }
    /** }}} */

    /**
     * Set the test status of this message.
     *
     * For falsy values, testing is off and the mail will be actually sent.
     * For a string looking like an email address, the mail is sent to that
     * address instead (e.g. joe@developer.com).
     * For other truthy values, the mail is simply dumped to STDOUT.
     *
     * @param mixed $test Falsy, truthy or string containing address.
     * @return Emily\Email $this
     */
    public function setTest($test)
    {
        $this->test = $test;
    }

    /**
     * Set variables in an array of key/value pairs.
     * The keys are replaced using {{ key }} markers in the mails as per Twig
     * default syntax.
     *
     * @param array $variables Array of key/value pairs
     * @return Emily\Email $this
     */
    public function setVariables(array $variables = [])
    {
        foreach ($variables as $key => $value) {
            $this->variables[$key] = $value;
        }
        return $this;
    }

    /**
     * Retrieve a specific variable's value by its name.
     *
     * @param string $name Name of the variable.
     * @return mixed The value, or null if unset.
     */
    public function getVariable($name)
    {
        return isset($this->variables[$name]) ?
            $this->variables[$name] :
            null;
    }

    public function headers(array $headers = [])
    {
        $this->headers = $headers + $this->headers;
        return $this;
    }

    /**
     * Load the Twig template contained in $template. 
     */
    public function loadTemplate($template)
    {
    }

    public function setSource($mail)
    {
        $twig = function(&$str) {
            $str = preg_replace('@\{\$(\w+)\}@ms', '{{ \\1 }}', $str);
        };
        try {
            $data = $this->adapter->row(
                "monolyth_mail m
                 LEFT JOIN monolyth_mail_template t ON t.id = m.template
                    AND t.language = m.language",
                ['m.*', 't.html AS thtml', 't.plain AS tplain'],
                [
                    'm.id' => $mail,
                    'm.language' => self::language()->current->id,
                ]
            );
            if (!$data['thtml']) {
                $data['thtml'] = '{{ content }}';
            }
            if (!$data['tplain']) {
                $data['tplain'] = '{{ content }}';
            }
            foreach ([
                'thtml', 'html', 'tplain', 'plain', 'subject', 'sender',
            ] as $twigify) {
                $twig($data[$twigify]);
            }
            foreach ([
                self::TYPE_HTML => 'html',
                self::TYPE_PLAIN => 'plain',
            ] as $id => $type) {
                $d = $data[$type];
                if ($id == self::TYPE_PLAIN) {
                    $d = html_entity_decode($d, ENT_COMPAT, 'UTF-8');
                    $d = strip_tags($d);
                }
                $this->content[$id] = str_replace(
                    '{{ content }}',
                    $d,
                    $data["t$type"]
                );
            }
            $this->headers['Subject'] = strip_tags($data['subject']);
            $this->headers['From'] = $data['sender'];
            $this->variables['subject'] = $data['subject'];
            $this->variables['from'] = $data['sender'];
        } catch (adapter\sql\NoResults_Exception $e) {
            $this->content[self::TYPE_HTML] =
            $this->content[self::TYPE_PLAIN] = '{{ content }}';
        }
        return $this;
    }

    /**
     * Send out the mail using all specified values.
     *
     * @param string $to The address to send to.
     */
    public function send($to)
    {
        $variables = $this->variables;
        foreach ($variables as $key => $value) {
            if (!is_string($value) && is_callable($value)) {
                // PHP can display some weird behaviour when $value happens to
                // contain a string matching a function name.
                try {
                    $variables[$key] = $value($this, $to);
                } catch (ErrorException $e) {
                }
            }
        }
        foreach ($this->content as $type => $content) {
            switch ($type) {
                case self::TYPE_HTML: $fn = 'setHTMLbody'; break;
                case self::TYPE_PLAIN:
                    $fn = 'setTXTbody';
                    foreach ($variables as &$variable) {
                        if (!is_scalar($variable)) {
                            continue;
                        }
                        $variable = (string)$variable;
                        $variable = $this->purify($variable);
                        $variable = $this->stripSmart($variable);
                    }
                    break;
            }
            do {
                $content = $this->twig->render($content, $variables);
            } while (preg_match('@{{ \w+ }}@ms', $content));
            $replace = function($matches) {
                return str_replace(
                    $matches[1],
                    $this->httpimg($matches[1]),
                    $matches[0]
                );
            };
            foreach ([
                '@src="(/.*?)"@ms',
                '@url\([\'"]?(/.*?)[\'"]?\)@ms',
            ] as $regex) {
                $content = preg_replace_callback($regex, $replace, $content);
            }
            $content = call_user_func($this->parser, $content);
            // This prolly never happens now:
            $content = preg_replace('@{$\w+}@', '', $content);
            $this->mail->$fn($content);
        }
        foreach (['Subject', 'From'] as $header) {
            do {
                $this->headers[$header] = $this->twig->render(
                    $this->headers[$header],
                    $variables
                );
            } while (preg_match('@{{ \w+ }}@ms', $this->headers[$header]));
        }
        $body = $this->mail->get();
        if ($this->project['test']) {
            if (isset($this->project['testmail'])) {
                $to = $this->project['testmail'];
            } else {
                $to = null;
            }
        }
        if (isset($to)) {
            $this->send->send(
                $to,
                $this->mail->headers($this->headers, true),
                $body
            );
        }
        return $this;
    }
}

