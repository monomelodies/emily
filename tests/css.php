<?php

/** Test adding inline CSS */
return function () : Generator {
    /** Test a message with CSS */
    yield function () : Generator {
        $loader = new Twig_Loader_Array([
            'mail' => <<<EOT
{% extends 'template' %}
{% block subject %}Testing{% endblock subject %}
{% block sender %}marijn@monomelodies.nl{% endblock sender %}
{% block sender_name %}Marijn Ophorst{% endblock sender_name %}
{% block html_content %}
<a href></a>
<div>
    <a href></a>
    <p>
        <a href></a>
    </p>
</div>
{% endblock html_content %}

EOT
            ,
            'template' => <<<EOT
{% block html %}
<!doctype html>
<html>
    <body>
        {% block html_content %}{% endblock html_content %}
    </body>
</html>
{% endblock html %}

EOT
        ]);
        $twig = new Twig_Environment(
            $loader,
            ['cache' => false, 'debug' => true]
        );
        $css = <<<EOT
div {
    border: 1px solid red;
    }
div p, div a {
    color: blue;
    }
div p a {
    font-weight: bold;
    }
EOT;
        $msg = new Monomelodies\Emily\Message($twig, $css);
        $msg->loadTemplate('mail');
        $expected = <<<EOT
<!doctype html>
<html><body>
        <a href></a>
<div style="border: 1px solid red;">
    <a href style="color: blue;"></a>
    <p style="color: blue;">
        <a href style="color: blue; font-weight: bold;"></a>
    </p>
</div>
    </body></html>
EOT;
        /** The HTML part is set correctly */
        yield function () use ($msg, $expected) {
            $html = $msg->getHtml();
            assert($html == $expected);
        };
    };
};

