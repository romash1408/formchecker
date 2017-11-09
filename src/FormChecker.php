<?php
namespace R14;

class FormChecker
{

	const STYLES = [
		// Table
		"table" => "width='100%' style='width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 auto; border: 1px solid rgba(0,0,0,0.5);'",
		// Header cell
		"head" => "width='20%' style='background-color: #DDDDDD; padding: 15px; border: 1px solid rgba(0,0,0,0.5);'",
		// Body cell
		"body" => "style='padding: 15px; border: 1px solid rgba(0,0,0,0.5);' align='left'",
		// link
		"link" => "style='color: black!important; text-decoration: none; border-bottom: 1px solid rgba(0,0,0,0.5); padding-bottom: 2px;' target='_blank'",
	];

	const EXCEPTIONS = [
		"UNDEFINED_FORM" => 'Undefined form "$formname"',
		"WRONG_CAPTCHA" => 'Google recaptcha failed "$codes"',
		"REQUIRED_FIELD_EMPTY" => 'Field "$field" must not be empty',
		"WRONG_EMAIL" => 'Incorrect email address format',
		"WRONG_PHONE" => 'Incorrect phone number format',
	];

	protected $forms;
	protected $send;
	protected $styles;
	protected $exceptions;
	public $grecaptcha = null;

	public function __construct (array $forms, array $config = [])
	{
		$this->forms = $forms;

		if (!array_key_exists("send", $config) || !($config["send"] instanceof \Closure))
		{
			$config["send"] = function ($mail)
			{
				return @mail($mail["to"], $mail["subject"], $mail["body"], 'MIME-Version: 1.0\r\nContent-type: text/html; charset="windows-utf-8"');
			};

		}
		$this->send = $config["send"];

		if (!array_key_exists("exceptions", $config) || !is_array($config["exceptions"]))
		{
			$config["exceptions"] = [];
		}
		$this->exceptions = array_merge(self::EXCEPTIONS, $config["exceptions"]);

		if (!array_key_exists("styles", $config) || !is_array($config["styles"]))
		{
			$config["styles"] = [];
		}
		$this->styles = array_merge(self::STYLES, $config["styles"]);
	}

	public function work (string $formname = "default", array $data = null)
	{
		if ($data === null)
		{
			$data = array_merge($_FILES, $_POST);
		}

		if (!array_key_exists($formname, $this->forms))
		{
			$this->exception("UNDEFINED_FORM", [
				"formname" => $formname,
			]);
		}

		$form = $this->forms[$formname];

		if ($this->grecaptcha !== null)
		{
			$grecaptcha = json_decode(post("https://www.google.com/recaptcha/api/siteverify", [
				"secret" => $this->grecaptcha,
				"response" => $_POST["grecaptcha-response"],
				"remoteip" => $_SERVER['REMOTE_ADDR']
			]));
			
			if (!$grecaptcha->success)
			{
				$this->exception("WRONG_CAPTCHA", [
					"codes" => print_r($grecaptcha->{"error-codes"}, 1),
				]);
			}
		}

		$mail = [
			"to" => $form["to"],
			"subject" => $form["subject"],
			"body" => '<h3>Data filled by user:</h3><table ' . $this->styles["table"] . '>',
		];

		$filled = [];

		foreach ($form["fields"] as $field => $props)
		{
			$props = array_merge([
				"type" => "text",
				"placeholder" => $field,
				"required" => false,
			], $props);

			$value = "";
			if (array_key_exists($field, $data))
			{
				$value = htmlspecialchars($data[$field]);
			}

			if (array_key_exists("value", $props))
			{
				$value = $props["value"]($value);
			}

			if (empty($value))
			{
				if ($props["required"])
				{

					$this->exception("REQUIRED_FIELD_EMPTY", [
						"field" => $props["placeholder"]
					]);

				} else {

					continue;

				}
			}

			$filled[$field] = $value;
			
			switch ($props["type"])
			{
			case "text":

				$mail["body"] .= "<tr>
					<td " . $this->styles["head"] . " align='right'>{$props['placeholder']}:</td>
					<td " . $this->styles["body"] . ">$value</td>
				</tr>";

				break;

			case "textarea":

				$mail["body"] .= "
				<tr><td " . $this->styles["head"] . " colspan='2' align='center'>{$props['placeholder']}:</td></tr>
				<tr><td " . $this->styles["body"] . " colspan='2'>" . str_replace("\n", '<br />', $value) . "</td></tr>
				";

				break;

			case "email":

				$address = explode("@", $value);
				if (
					count($address) < 2 ||
					count(dns_get_record(array_pop($address), DNS_MX)) === 0
				) {
					$this->exception("WRONG_EMAIL");
				}

				$mail["body"] .= "<tr>
					<td " . $this->styles["head"] . " align='right'>{$props['placeholder']}:</td>
					<td " . $this->styles["body"] . ">
						<a " . $this->styles["link"] . " href='mailto:$value'>$value</a>
					</td>
				</tr>";

				break;

			case "tel":

				$phone = str_replace(str_split("+ ()-."), '', $value);
				if (
					strlen($phone) < 11 ||
					!is_numeric($phone)
				) {
					$this->exception("WRONG_PHONE");
				}

				$mail["body"] .= "<tr>
					<td " . $this->styles["head"] . " align='right'>{$props['placeholder']}:</td>
					<td " . $this->styles["body"] . ">
						<a " . $this->styles["link"] . " href='tel:$value'>$value</a>
					</td>
				</tr>";

				break;

			}
		}

		if (array_key_exists("HTTP_REFERER", $_SERVER))
		{
			$referer = $_SERVER["HTTP_REFERER"];

		} else {
			$referer = "HTTP_REFERER would be here";	
		}

		$mail["body"] .= '</table><p>From page: <b>' . $referer . '</b></p>';

		$this->send->call($this, $mail);

		if (isset($form["callback"]))
		{
			$form["callback"]($filled);
		}

		if (array_key_exists("autosend", $form))
		{
			$autosend = $form["autosend"];

			if (
				!array_key_exists("verify", $autosend) ||
				$autosend["verify"]()
			) {
				$mail["to"] = $autosend["address"]();
				$mail["subject"] = $autosend["subject"]();
				$mail["body"] = $autosend["template"]();
				$this->send->call($this, $mail);
			}

		}

		return 0;
	}

	public function exception ($exception, $args = [])
	{
		if (!array_key_exists($exception, $this->exceptions)) 
		{
			throw new FormException ("Undefined exception '$exception' in FormChecker with args: " . print_r($args, 1));
		}

		$str = $this->exceptions[$exception];
		foreach ($args as $key => $value){
			$str = str_replace("$" . $key, $value, $str);
		}
		throw new FormException ($str);
	}

	public static function post ($href, $data)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $href);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$ret = curl_exec($ch);
		curl_close ($ch);
		return $ret;
	}
}