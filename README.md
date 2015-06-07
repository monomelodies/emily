# Emily
Twig and Swift_Mailer-based mailer

Emily is an extension to [Swift_Mailer](http://swiftmailer.org) adding support
for the following:

- Load Twig templates either from disk or some other source (database etc.)
- Easy environment setting (mails sent during development get redirected)
- Integration with SASS-based variables
- Auto-insert guesstimated text part if only HTML was given

## Usage
At the most basic level, you can simply use Emily like you would Swift:

```php
<?php

use Emily\Email;

$message = Email::newInstance('my subject')
    ->setBody('Howdy!')
    ->addPart('<p>Howdy!</p>', 'text/html')
    ->setFrom(['joe@developer.com' => 'Joe Developer'])
    ->setTo(['alice@wonderland.com' => 'Alice']);

```

The message can then be sent using regular Swift transports
([see their documentation](http://swiftmailer.org/docs/sending.html)).

## Templates and variables
Obviously, the above isn't particularly useful. Where Emily shines is in its
inclusion of [Twig templates](http://twig.sensiolabs.org) as a source for
emails:

```php
<?php

$message->setTemplate(<<<EOT
{% block subject %}my subject{% endblock %}
{% block html %}<p>Howdy!</p>{% endblock %}
{% block body %}Howdy!{% endblock %}

EOT
);

```

