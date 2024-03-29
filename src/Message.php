<?php

/**
 * @package Monomelodies\Emily
 */

namespace Monomelodies\Emily;

use Twig\{ Environment, Error\RuntimeError, TemplateWrapper };
use Symfony\Component\Mime\{ Email, Address };
use DomainException;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class Message
{
    const TYPE_HTML = 1;
    const TYPE_PLAIN = 2;

    private array $variables = [];

    private Environment $twig;

    private ?string $css;

    private $msg;

    private ?string $subject = null;

    private ?string $sender = null;

    private ?string $sender_name = null;

    private ?string $plain = null;

    private ?string $html = null;

    private bool $compiled = false;

    private TemplateWrapper $template;

    /**
     * Constructor. Inject your desired Twig\Environment.
     *
     * @param Twig\Environment $twig The Twig environment Emily should use.
     * @param string $css Optional string of CSS to use for inline styles.
     * @return void
     */
    public function __construct(Environment $twig, string $css = null)
    {
        $this->twig = $twig;
        $this->css = $css;
        $this->clean();
    }

    /**
     * Clean the current message by reloading.
     *
     * @return void
     */
    public function clean() : void
    {
        $this->msg = new Email;
    }

    /**
     * Load the specified template. Where you get it from (file system, database
     * etc.) is the concern of the $loader you instantiated the message with.
     *
     * @param string $name Name of the template.
     * @return Emily\Message $this
     */
    public function loadTemplate(string $name) : Message
    {
        $this->template = $this->twig->load($name);
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
    public function setVariables(array $variables) : Message
    {
        $this->compiled = false;
        $this->variables = $variables + $this->variables;
        return $this;
    }

    /**
     * Compile the message. Replace variables and apply CSS.
     *
     * @return void
     */
    public function compile() : void
    {
        static $cssToInlineStyles;
        if ($this->compiled) {
            return;
        }
        foreach ([
            'subject',
            'sender',
            'sender_name',
            'plain',
            'html',
        ] as $block) {
            ob_start();
            try {
                $this->template->displayBlock(
                    $block,
                    $this->variables
                );
                $content = ob_get_clean();
                if (strlen(trim($content))) {
                    $this->$block = $content;
                    if ($block == 'html' && isset($this->css)) {
                        if (!isset($cssToInlineStyles)) {
                            $cssToInlineStyles = new CssToInlineStyles;
                        }
                        $this->html = $cssToInlineStyles->convert($this->html, $this->css);
                    }
                }
            } catch (RuntimeError $e) {
            }
        }
        $this->compiled = true;
    }

    /**
     * Get current subject.
     *
     * @return string|null The subject, or null if not set.
     */
    public function getSubject() :? string
    {
        $this->compile();
        return $this->subject;
    }

    /**
     * Get current sender.
     *
     * @return string|null The sender, or null if not set.
     */
    public function getSender() :? string
    {
        $this->compile();
        return $this->sender;
    }

    /**
     * Get current sender name. Defaults to "sender" (the email address) if
     * not provided.
     *
     * @return string The sender's name, or the email address if not set.
     */
    public function getSenderName() : string
    {
        $this->compile();
        return $this->sender_name ?? $this->sender;
    }

    /**
     * Get the plaintext body. This is "automagically" inferred from the HTML
     * part if not supplied explicitly (via {% block plain %}).
     *
     * @return string The body text.
     */
    public function getBody() : string
    {
        $this->compile();
        if (!isset($this->plain)) {
            $this->plain = $this->strip($this->html);
        }
        return $this->plain;
    }

    /**
     * Strip HTML from the supplied text, and replace with something that sort
     * of makes sense in a plaintext context.
     *
     * @param string $txt
     * @return string
     */
    public function strip(string $txt) : string
    {
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
        return trim(preg_replace(
            '@^\s+@m',
            "\n",
            html_entity_decode(
                strip_tags($txt),
                ENT_QUOTES,
                'UTF-8'
            )
        ));
    }

    /**
     * Get the HTML body, if supplied.
     *
     * @return string|null The HTML part's contents, or null if no such block
     *                     was defined.
     */
    public function getHtml() :? string
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
    public function getVariable(string $name)
    {
        return isset($this->variables[$name]) ?
            $this->variables[$name] :
            null;
    }

    /**
     * Get the complete, compiled message ready for sending using a
     * Swift_Transport.
     *
     * @return Symfony\Component\Mime\Email An email message with all relevant
     *  values filled. Feel free to add stuff like Ccs afterwards!
     * @see https://symfony.com/doc/current/mailer.html
     */
    public function get() : Email
    {
        $this->compile();
        $this->msg->subject($this->subject)
            ->from(new Address($this->sender, $this->getSenderName()))
            ->text($this->plain);
        if ($html = $this->getHtml()) {
            $this->msg->html($html, 'text/html');
        }
        return $this->msg;
    }

    /**
     * Proxy all other calls to the underlying message, if such a method exists.
     *
     * @return mixed
     * @throws DomainException if the method does not exist.
     */
    public function __call($fn, array $args = [])
    {
        if (method_exists($this->msg, $fn)) {
            return call_user_func_array([$this->msg, $fn], $args);
        }
        throw new DomainException("Method $fn does not exist");
    }
}

