# FormChecker
## Description
Makes similar checking of form fields filled on your web site.
It verifies required fields, including email addresses (checks `@` existing and hostname MX record).    
After that html report is generated and sent by [mail()](http://php.net/manual/ru/function.mail.php),
or user custom function, callback called with filtered fields and autosend is sent by same method.
## Install
Via Composer

``` bash
$ composer require romash1408/formchecker
```

## Example

``` php
require "vendor/autoload.php";

use R14\FormChecker;
use R14\FormException;

$usermail;

$checker = new FormChecker(
    $forms = [
		"mainform" =>
		[
			"subject" => "Test main form",
			"fields" =>
			[
                "name" => [
                    "placeholder" => "Name",
                ],
                "phone" => [
                    "type" => "tel",
                    "placeholder" => "Phone number",
                    "required" => true,
                ],
                "email" => [
                    "type" => "email",
                    "placeholder" => "E-mail",
                    "required" => true,
                ],
                "list" => [
                    "type" => "list",
                    "placeholder" => "List",
                    "required" => true,
                    "list" => [
                        "One", "Two", "Three",
                    ],
                ],
			],
			"callback" => function($fields) use(&$usermail)
			{

                $usermail = $fields["email"];
                // We can send post request with filtered and checked data to CRM server, if needed
                // $fields["key"] = "123456";
                // Formsender::post("https://crm-server.com", $fields);
			},
			"autosend" =>
			[
				"template" => function()
				{

                    return <<<HTML
<h3>Thank you for your request!</h3>
HTML;

				},
				"address" => function() use(&$usermail)
				{
					return [ $usermail ];
				},
				"subject" => function()
				{
					return "Answer to user";
				},
			],
			"to" => [ "admin@server" ],
        ],
        "contactform" => [
            "subject" => "Contact form",
            "fields" => [
                "author" => [
                    "type" => "email",
                    "placeholder" => "Author e-mail",
                    "required" => true,
                ],
                "message" => [
                    "type" => "textarea",
                    "placeholder" => "Message",
                    "required" => true,
                ],
            ],
            "to" => [ "moder@gmail.com" ],
        ],
    ],
    $config = [
       "send" => function ($mail) {
            echo '
            <h2>Sending mail to ' . implode(", ", $mail["to"]) . ' with subject "' . $mail["subject"] . '"</h2>
            ' . $mail["body"] . '
            ';
        },
    ]
);
```

Now we have `$checker` variable with information about all our forms and send function.
To make magic just call `$checker->work()` with formname (`"default"` by default) and
form fields array (`$_POST` + `$_FILES` by default), like this:

```php
try
{
    $checker->work($_POST["formname"]);
}
catch (FormException $e)
{
    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';
}
```

### #1
``` php
$_POST = [
    "formname" => "mainform",
];
```

#### Output:
> ![Field Phone number must not be empty](https://romash1408.ru/untouchable/github-formchecker/readme/EMPTY_FIELD.jpg "Field Phone number must not be empty")

### #2
``` php
$_POST = [
    "formname" => "mainform",
    "phone" => "+7 (999) 999-99-9",
    "email" => "test",
];

try {

    $checker->work($_POST["formname"]);

} catch (FormException $e) {

    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';

}
```

#### Output:
> ![Incorrect phone number format](https://romash1408.ru/untouchable/github-formchecker/readme/WRONG_PHONE.jpg "Incorrect phone number format")

### #3
``` php
$_POST = [
    "formname" => "mainform",
    "phone" => "+7 (999) 999-99-99",
    "email" => "test@gmail.co",
];

try {

    $checker->work($_POST["formname"]);

} catch (FormException $e) {

    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';

}
```

#### Output:
> ![Incorrect email address format](https://romash1408.ru/untouchable/github-formchecker/readme/WRONG_EMAIL.jpg "Incorrect email address format")

### #4
``` php
$_POST = [
    "formname" => "mainform",
    "name" => "Tester",
    "phone" => "+7 (999) 999-99-99",
    "email" => "test@gmail.com",
];

try {

    $checker->work($_POST["formname"]);

} catch (FormException $e) {

    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';

}
```

#### Output:
> ![Field List must not be empty](https://romash1408.ru/untouchable/github-formchecker/readme/EMPTY_LIST.jpg "Field List must not be empty")

### #5
``` php
$_POST = [
    "formname" => "mainform",
    "name" => "Tester",
    "phone" => "+7 (999) 999-99-99",
    "email" => "test@gmail.com",
    "list" => [
        "One", "Four",
    ],
];

try {

    $checker->work($_POST["formname"]);

} catch (FormException $e) {

    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';

}
```

#### Output:
> ![Did not found Four in List](https://romash1408.ru/untouchable/github-formchecker/readme/WRONG_LIST.jpg "Did not found Four in List")

### #6
``` php
$_POST = [
    "formname" => "mainform",
    "name" => "Tester",
    "phone" => "+7 (999) 999-99-99",
    "email" => "test@gmail.com",
    "list" => [
        "One", "Three",
    ],
];

try {

    $checker->work($_POST["formname"]);

} catch (FormException $e) {

    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';

}
```

#### Output:
> ![Result of sending main form](https://romash1408.ru/untouchable/github-formchecker/readme/MAIN_FORM_SENT-2.jpg "Result of sending main form")

### #7
``` php
$_POST = [
    "formname" => "contactform",
    "author" => "work@romash1408.ru",
    "message" => <<<TEXT
Helo, this is a test of FormChecker.
It is using <a href='http://php.net/manual/ru/function.htmlspecialchars.php'>htmlspecialchars</a>,
Su users can not <script>hack(you);</script>
TEXT
];

try {

    $checker->work($_POST["formname"]);

} catch (FormException $e) {

    echo '<h2 style="color: red">' . $e->getMessage() . '</h2>';

}
```

#### Output:
> ![Result of sending contact form](https://romash1408.ru/untouchable/github-formchecker/readme/CONTACT_FORM_SENT.jpg "Result of sending contact form")
