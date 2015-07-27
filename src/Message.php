<?php

/**
 * @package Emily
 */

namespace Emily;

use Twig_Environment;
use Swift_Message;

class Message
{
    const TYPE_HTML = 1;
    const TYPE_PLAIN = 2;

    private $headers = [];
    private $variables = [];
    private $content = [];
    protected $twig;
    private $subject = null;
    private $sender = null;
    private $plain = null;
    private $html = null;
    private $test = false;
    private $compiled = false;

    /**
     * Constructor. Inject your desired Twig_Environment.
     *
     * @param Twig_Environment $twig The Twig environment Emily should use.
     */
    public function __construct(Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Load the specified template. Where you get it from (file system, database
     * etc.) is the concern of the $loader you instantiated the message with.
     *
     * @param string $name Name of the template.
     * @return Emily\Message $this
     */
    public function loadTemplate($name)
    {
        $this->template = $this->twig->loadTemplate($name);
        return $this;
    }

    /**
     * Set variables in an array of key/value pairs.
     * The keys are replaced using {{ key }} markers in the mails as per Twig
     * default syntax.
     *
     * @param array $variables Array of key/value pairs
     * @return Emily\Message $this
     */
    public function setVariables(array $variables)
    {
        $this->compiled = false;
        $this->variables = $variables + $this->variables;
        return $this;
    }

    public function compile()
    {
        if ($this->compiled) {
            return;
        }
        foreach (['subject', 'sender', 'plain', 'html'] as $block) {
            ob_start();
            $this->template->displayBlock(
                $block,
                $this->variables
            );
            $content = ob_get_clean();
            if (strlen(trim($content))) {
                $this->$block = $content;
            }
        }
        $this->compiled = true;
    }

    /**
     * Get current subject.
     *
     * @return string|null The subject, or null if not set.
     */
    public function getSubject()
    {
        $this->compile();
        return $this->subject;
    }

    /**
     * Get current sender.
     *
     * @return string|null The sender, or null if not set.
     */
    public function getSender()
    {
        $this->compile();
        return $this->sender;
    }

    /**
     * Get the plaintext body. This is "automagically" inferred from the HTML
     * part if not supplied explicitly (via {% block plain %}).
     */
    public function getBody()
    {
        $this->compile();
        if (is_null($this->plain)) {
            $txt = $this->html;
            // Replace <br/> (in any variant) with newline.
            $txt = preg_replace('@<br\s+?/?>@i', "\n", $txt);
            
            // Replace paragraph elements with newlines.
            $txt = preg_replace(
                '@<[Pp].*?>(.*?)</[Pp]>@ms',
                "\n$1\n",
                $txt
            );
            // ...but let's not overdo it...
            $txt = str_replace("\n\n\n", "\n\n", $txt);
            
            // Replace <del> tags with \\1^W.
            $txt = preg_replace_callback(
                '@<del.*?>(.*?)</del>@msi',
                function($match) {
                    return preg_replace("@\s+@ms", "^W", $match[1]);
                },
                $txt
            );
            
            // Replace anchors with something intelligent. For the best result
            // you'll probably want to manually load a text/plain template, but at
            // least this is better than nothing.
            $replace = [];
            if (preg_match_all(
                '@<a.*?href="(.*?)">(.*?)</a>(.*?)[!.?]@msi',
                $txt,
                $matches
            )) {
                foreach ($matches[0] as $i => $match) {
                    $replace[$match] = $matches[2][$i] // Part between <a> tags.
                    .$matches[3][$i] // Part after up to end of sentence.
                    .":\n{$matches[1][$i]}\n"; // URI on separate line.
                }
            }
            // Replace leftover anchors.
            $txt = preg_replace_callback(
                '@<a.*?href="(.*?)">(.*?)</a>@msi',
                function($match) {
                    return "{$match[1]}\n{$match[2]}";
                },
                $txt
            );
            
            // Replace <b> or <strong> with *..*.
            $txt = preg_replace(
                '@<(b|strong).*?>(.*?)</\1>@msi',
                "*\\2*",
                $txt
            );
            // Replace <i> or <em> with /../.
            $txt = preg_replace(
                '@<(i|em).*?>(.*?)</\1>@msi',
                "/\\2/",
                $txt
            );
            $this->plain = trim(preg_replace(
                '@^\s+@m',
                "\n",
                html_entity_decode(
                    strip_tags($txt),
                    ENT_QUOTES,
                    'UTF-8'
                )
            ));
        }
        return $this->plain;
    }

    /**
     * Get the HTML body, if supplied.
     */
    public function getHtml()
    {
        $this->compile();
        return $this->html;
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

    /**
     * Get the complete, compiled message ready for sending using a
     * Swift_Transport.
     *
     * @return Swift_Message A Swift message with all relevant values filled.
     * @see http://swiftmailer.org/docs/sending.html#transport-types
     */
    public function get()
    {
        $this->compile();
        $msg = Swift_Message::newInstance($this->subject)
            ->setFrom($this->sender)
            ->setBody($this->getBody());
        if ($html = $this->getHtml()) {
            $msg->addPart($this->getHtml(), 'text/html');
        }
        return $msg;
    }
}

