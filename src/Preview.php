<?php

namespace Emily;

class Preview
{
    protected $emily;

    public function __construct(Message $emily)
    {
        $this->emily = $emily;
    }

    public function render()
    {
        $template = isset($_POST['template']) ? $_POST['template'] : null;
        $vars = isset($_POST['vars']) ? $_POST['vars'] : null;
        ob_start();
        echo <<<EOT
<!doctype html>
<html>
    <head>
        <title>Emily message preview</title>
        <style>
            html, body {
                min-height: 100%;
                margin: 0;
                padding: 0;
                font: 16px Verdana, sans-serif;
                overflow: hidden;
                }
            #meta {
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                right: 80%;
                min-height: 100%;
                background: #000;
                color: #fff;
                }
            input, textarea {
                width: 100%;
                box-sizing: border-box;
                font: 12px Verdana, sans-serif;
                padding: 2px 4px;
                }
            textarea {
                min-height: 20em;
                resize: vertical;
                margin-top: 12px;
                }
            button {
                float: right;
                margin: 12px 0;
                cursor: pointer;
                }
            #meta fieldset {
                min-height: 100%;
                padding: 12px;
                border: 0;
                }
            #message {
                position: fixed;
                left: 20%;
                right: 0;
                top: 0;
                bottom: 0;
                padding: 12px;
                overflow-y: scroll;
                background: #eee;
                }
            h1 {
                margin: 0 0 .5em;
                font-size: 100%;
                }
            #message > div {
                background: #fff;
                margin-bottom: .5em;
                }
            #text {
                font-family: monospace;
                white-space: pre;
                font-size: 80%;
                padding: 1em;
                }
        </style>
    </head>
    <body>
        <form id="meta" method="post"><fieldset>
            <input type="text" name="template" value="$template">
            <textarea name="vars">$vars</textarea>
            <button type="submit">Refresh</button>
        </fieldset></form>
        <div id="message">

EOT;
        if (isset($template)) {
            $this->emily->loadTemplate($template);
            if (isset($vars)) {
                $this->emily->setVariables(json_decode($vars, true));
            }
            printf(
                '<h1>Plain</h1><div id="text">%s</div>',
                preg_replace('@^( |\t)+@m', '', $this->emily->getBody())
            );
            printf(
                '<h1>HTML</h1><div id="html">%s</div>',
                $this->preview($this->emily->getHtml())
            );
        }
        echo <<<EOT
        </div>
    </body>
</html>

EOT;
        return ob_get_clean();
    }

    protected function preview($html)
    {
        if (!preg_match('@(<body.*?/body>)@ims', $html, $match)) {
            return $html;
        }
        return preg_replace('@<(/?)body@im', '<\\1div', $match[1]);
    }
}

