# Mail Module

Message model, mailer drivers, and facade access.

## Example

```php
<?php

use Vortex\Mail\Mail;
use Vortex\Mail\MailMessage;

[$fromAddress, $fromName] = Mail::defaultFrom();

Mail::send(new MailMessage(
    from: [$fromAddress, $fromName],
    to: [['dev@example.com', 'Dev']],
    subject: 'Welcome',
    textBody: "Hello from Vortex",
    htmlBody: '<p>Hello from <strong>Vortex</strong></p>',
));
```

## Notes

- Mailer driver is configured in `config/mail.php`.
- Drivers include SMTP, native mail, logging, and null mailer.
