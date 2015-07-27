# Emily
Twig and Swift_Mailer-based mailer

Emily is an extension to [Swift_Mailer](http://swiftmailer.org) adding support
for the following:

- Load Twig templates either from disk or some other source (database etc.);
- Define blocks for various sections (subject, sender, body etc.) allowing
  all mail data to be stored in one template;
- Auto-insert guesstimated text part if only HTML was given.

## Usage
Define an `Emily\Message` and setup a Twig template:

```php
<?php

use Emily\Message;

$loader = new Twig_Loader_Filesystem('/path/to/templates');
$twig = new Twig_Environment($loader);

// Inject the Twig_Environment to use via the Message constructor:
$msg = new Message($twig);
$msg->loadTemplate('path/to/template.html.twig');

// When done, export the Swift_Message using Emily\Message::get
$swift = $msg->get();
// Now, send $swift using regular transport.

```

([See Swift documentation for details on how to send messages using a
`Swift_Transport` of your choice.](http://swiftmailer.org/docs/sending.html))

## Templates and variables
In your template, you can define a number of Twig "blocks" for the various
parts of your message:

```twig
{% block subject %}This is the message subject!{% endblock subject %}
{% block plain %}This is the plaintext content.{% endblock plain %}
{% block html %}This is the <b>HTML</b>.{% endblock html %}
{% block sender %}marijn@monomelodies.nl{% endblock sender %}
{% block sender_name %}Marijn Ophorst{% endblock sender_name %}

```

You can also use regular Twig variables:

```twig
{% block plain %}Hello, {{ firstname }}.{% endblock plain %}

```

...which you define using the `Emily\Message::setVariables` method:

```php
<?php

//...
$msg->setVariables(['firstname' => 'Marijn']);

```

Variables can be used in any block, including the subject.

Of course, you can also let your messages extend a more global template using
normal Twig extending rules. Templates can also define _other_ blocks (e.g. some
kind of sidebar in your template with the latest news posts at the moment of
sending - use your imagination here). Depending on your `Twig_Environment`, you
might also use translations etc.

## Setting the recipient, adding attachments etc.
The return value of `Emily\Message::get` is simply a `Swift_Message` (with all
variables replaced), so you can do what you want with it. Note that after any
variable change, you'll need to re-call `get` to receive an updated version.

