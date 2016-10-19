<?php

class BasicTest extends PHPUnit_Framework_TestCase
{
    public function testSimpleMessage()
    {
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
        $this->assertEquals('Testing Emily!', $msg->getSubject());
        $this->assertEquals('marijn@monomelodies.nl', $msg->getSender());
        $msg->setVariables(['product' => 'Swift_Mailer']);
        $this->assertEquals('Testing Swift_Mailer!', $msg->getSubject());
        $this->assertEquals(<<<EOT
Test template for Swift_Mailer

Hi there!

hugs and kisses
EOT
            ,
            $msg->getBody()
        );
    }

    public function testPlainAndHtml()
    {
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
        $this->assertEquals('<p>Hi there!</p>', $msg->getHtml());
        $this->assertEquals(
            'This will contain something different.',
            $msg->getBody()
        );
    }
}

