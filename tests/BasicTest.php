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
{% block content %}
    <p>Hi there!</p>
{% endblock content %}

EOT
            ,
            'template' => <<<EOT
{% block html %}
<html>
    <body>
        <h1>Test template for {{ product }}</h1>
        {% block content %}{% endblock content %}
        <small>hugs and kisses</small>
    </body>
</html>

{% endblock html %}

EOT
        ]);
        $msg = new Emily\Message($loader, false);
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
{% block content %}
<p>Hi there!</p>{% endblock content %}

EOT
            ,
            'template' => <<<EOT
{% block html %}
{% block content %}{% endblock content %}
{% endblock html %}

{% block plain %}
This will contain something different.{% endblock plain %}

EOT
        ]);
        $msg = new Emily\Message($loader, false);
        $msg->loadTemplate('mail');
        $this->assertEquals('<p>Hi there!</p>', $msg->getHtml());
        $this->assertEquals(
            'This will contain something different.',
            $msg->getBody()
        );
    }
}

