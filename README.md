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

$msg = new Message(new Twig_Loader_Filesystem('/path/to/templates'));
$msg->loadTemplate('path/to/template.html.twig');
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

```

You can also use regular Twig variables:

```twig
{% block plain %}Hello, {{ firstname }}.{% endblock plain %}

```

...which you define using the `Message::setVariables` method:

```php
<?php

//...
$msg->setVariables(['firstname' => 'Marijn']);

```

Variables can be used in any block, including the subject.

Of course, you can also let your messages extend a more global template using
normal Twig extending rules.

## Setting the recipient, adding attachments etc.
The return value of `Emily\Message::get` is simply a `Swift_Message` (with all
variables replaced), so you can do what you want with it. Note that after any
variable change, you'll need to re-call `get` to receive and updated version.

