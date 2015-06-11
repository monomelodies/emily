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

$message
    ->addTemplate(
        'template-id',
        "
            {% block subject %}my subject{% endblock %}
            {% block html %}<p>Howdy, {{ name }}!</p>{% endblock %}
            {% block text %}Howdy, {{ name }}!{% endblock %}
        ",
        time()
    )
    ->setVariables(['name' => 'partner']);

```

Using a Twig template, you can specify all relevant parts in blocks. And of
course you can also extend and include other templates.

The 'template-id' can be referred to from other templates when extending:

```php
<?php

$message
    ->addTemplate(
        'main-template',
        "
            {% block html %}
                <img src="/my/logo.png">
                {{ parent() }}
            {% endblock %}
        ",
        time()
    )
    ->addTemplate(
        'message-template',
        "
            {% extends 'main-template' %}
            {% block html %}<p>Howdy!</p>{% endblock %}
        "
    );
```

> Emily leaves it to the developer to decide where they load their templates
> from. For instance, if your project offers a CMS to clients you might need to
> get them from a database instead of from file.

The third parameter to `Email::addTemplate` is the last-modified-timestamp. Twig
uses this internally to determine cache freshness.

## Sending a Twig templated email
For Twig-enabled message, the sending process is slightly different since we
need Emily to actually render the templates. So, instead calling `send` on the
mailer, we call `send` on the `Emily\Email` message and pass the transport to
send it with:

```php
<?php

$to = ['bob@builder.com', 'alice@wonderland.com'];
$message = new Emily\Email
    // setup stuff...
    ;
$transport = Swift_SmtpTransport::newInstance('localhost', 25);

foreach ($to as $recipient) {
    $message->setVariables(['email' => $recipient]);
    $message->send($transport, $recipient);
}

```

We could achieve the same thing by putting message creation inside the `foreach`
loop, but trust us: if you're blasting out mails to 1000+ users you don't want
to create an entire object with templates on each iteration.

