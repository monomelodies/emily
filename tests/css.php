<?php

/** Test adding inline CSS */
return function () : Generator {
    /** Test a simple message */
    yield function () : Generator {
        $loader = new Twig_Loader_Array([
            'mail' => <<<EOT
{% extends 'template' %}
{% block subject %}Testing {{ product }}!{% endblock subject %}
{% block sender %}marijn@monomelodies.nl{% endblock sender %}
{% block sender_name %}Marijn Ophorst{% endblock sender_name %}
{% block html_content %}
    <p>Hi there!</p>
{% endblock html_content %}

EOT
            ,
            'template' => <<<EOT
{% block html %}
<html>
    <body>
        <h1>Test template for {{ product }}</h1>
        {% block html_content %}{% endblock html_content %}
        <small>hugs and kisses</small>
    </body>
</html>

{% endblock html %}

EOT
        ]);
        $twig = new Twig_Environment(
            $loader,
            ['cache' => false, 'debug' => true]
        );
        $msg = new Monomelodies\Emily\Message($twig);
        $msg->loadTemplate('mail');
        $msg->setVariables(['product' => 'Emily']);

        /** Subject is set correctly */
        yield function () use ($msg) {
            assert('Testing Emily!' == $msg->getSubject());
        };
        /** Sender is set correctly */
        yield function () use ($msg) {
            assert('marijn@monomelodies.nl' == $msg->getSender());
        };
        $msg->setVariables(['product' => 'Swift_Mailer']);
        /** Subject can be overridden */
        yield function () use ($msg) {
            assert('Testing Swift_Mailer!' == $msg->getSubject());
        };
        /** Body is set correctly */
        yield function () use ($msg) {
            assert(<<<EOT
Test template for Swift_Mailer

Hi there!

hugs and kisses
EOT
                == $msg->getBody()
            );
        };
    };

    /** Test mixing plain and html */
    yield function () : Generator {
        $loader = new Twig_Loader_Array([
            'mail' => <<<EOT
{% extends 'template' %}
{% block html_content %}
<p>Hi there!</p>{% endblock html_content %}

EOT
            ,
            'template' => <<<EOT
{% block html %}
{% block html_content %}{% endblock html_content %}
{% endblock html %}

{% block plain %}
This will contain something different.{% endblock plain %}

EOT
        ]);
        $twig = new Twig_Environment(
            $loader,
            ['cache' => false, 'debug' => true]
        );
        $msg = new Monomelodies\Emily\Message($twig);
        $msg->loadTemplate('mail');
        /** The HTML part is set correctly */
        yield function () use ($msg) {
            assert('<p>Hi there!</p>' == $msg->getHtml());
        };
        /** The plain text part is set correctly */
        yield function () use ($msg) {
            assert('This will contain something different.' == $msg->getBody());
        };
    };
};

