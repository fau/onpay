<?php


/**
 * Интерфейс класса базы данных для взаимодействия с API Onpay
 *
 * @author fau
 * @version 1.0
 * @filesource
 * @namespace Onpay
 *
 */

namespace Fau\Onpay;

/**
 * Класс взаимодействия с API Onpay
 *
 * @author norgen
 * @version 1.0
 * @filesource
 * @namespace Onpay
 *
 */

class Onpay
{

	/**
	 * Пользовательский ключ API IN.
	 *
	 * Задается в настройках сайта в личном кабинете на сайте Onpay.
	 *
	 * @var string
	 */
	var $key = "";

	/**
	 * Адрес платежной формы Onpay. По умолчанию это логин пользователя на сайте onpay.
	 * @var string
	 */
	var $userform = "";

	/**
	 * Email администратора магазина.
	 *
	 * Если он задан, на него будут отправляться уведомления об операциях.
	 * Возможные варианты: email в формате mail@domen.ru | пусто
	 * @var string
	 */
	var $admin_email = "";

	/**
	 * 3-х символьное наименование валюты. Основная валюта ценника.
	 *
	 * TODO Нужны возможные значения.
	 * По умолчанию: RUR
	 * @var string
	 */
	var $currency = 'RUR';

	/**
	 * Принудительная конвертация платежей в валюту ценника.
	 *
	 * Если включена – все поступающие платежи будут конвертироваться в валюту ценника.
	 * Т.е. если в ссылке установлена стоимость 100RUR, а клиент оплатил с помощью USD – Вы получите на счет 100RUR.
	 * Если выключена, Вы получите ту валюту, которой платит клиент.
	 * Т.е. например, пользователь платит 3.5WMZ за Ваш товар стоимостью 100RUR –
	 * Вы получите 3.5WMZ на свой WMZ счет в системе OnPay (при этом уведомление по API будет содержать 100RUR).
	 *
	 * “yes” – включена “no” - выключена, по умолчанию включена.
	 *
	 * @var string
	 */
	var $convert = "yes";

	/**
	 * Вариант дизайна платежной формы
	 *
	 * Возможные варианты: 7, 8
	 * По умолчанию: форма №8.
	 * @var string
	 */
	var $form_num = "8";

	/**
	 * Комиссию платежной системы взымать с продавца.
	 *
	 * К стоимости заказа не будет прибавляться комиссия платежной системы на ввод.
	 * Возможные значения: true | false
	 * По умолчанию: false (комиссия взымается с покупателя)
	 * @var bool
	 */
	var $price_final = false;

	/**
	 * Язык отображения платежной формы.
	 *
	 * Есть возможность сделать форму на любом языке - для этого пишите запрос в службу поддержки.
	 * Возможные варианты: en | ru
	 * По умолчанию: ru
	 * @var string
	 */
	var $ln = "ru";

	/**
	 * Ссылка, на которую будет переадресован пользователь после успешного завершения платежа.
	 *
	 * Внимание! Не может содержать параметры запроса (все, что идет после «?» в ссылке).
	 * @var string
	 */
	var $url_success = "";

	/**
	 * Ссылка, на которую будет переадресован пользователь после неудачного завершения платежа.
	 *
	 * Внимание! Не может содержать параметры запроса (все, что идет после «?» в ссылке).
	 * @var string
	 */
	var $url_fail = "";

	/**
	 * Режим отладки.
	 *
	 * Если True, то все ссылки/запросы пишутся в log-файл в директории с классом.
	 * По умолчанию: true
	 * @var bool
	 */
	var $debug = false;

	/**
	 * После инициализации класса содержит ссылку на объект БД.
	 *
	 * TODO Нужно доработать.
	 * @var SQLite3|MySql object
	 */
	var $db;

	/**
	 * Содержит путь к директории класса.
	 * @var string
	 */
	var $classpath = __DIR__;

	/**
	 * @var string хранит ошибки
	 */
	var $error = '';
	var $log = '';


	/**
	 * Конструктор.
	 *
	 * Используется как:
	 * <code>
	 * $opy = new Onpay('internal_db', 'petyastore', 'ajsdansdbascasc');
	 * </code>
	 * @param string $db база данных
	 * @param string $username адрес формы onpay. Устанавливает Onpay::$userform
	 * @param string $key ключ API IN. Устанавливает Onpay::$key
	 */
	public function __construct($db, $username, $key)
	{
		$this->userform = $username;
		$this->key = $key;
		$this->db = $db;
        $result = $this->db->init();
        if ($result === false) {
            $this->err('База данных не создана из-за сл. ошибок: ' . $this->db->lastErrorMsg());
        } else {
            $this->dbg('Создана база данных onpay...');
        }
	}

    protected function makeMD5($summ, $currency, $orderId){
        $md5summ = $this->to_float($summ);
        $md5check = strtoupper(md5("fix;{$md5summ};{$currency};{$orderId};{$this->convert};{$this->key}"));
        return $md5check;
    }
	/**
	 * Функция генерирует ссылку или редирект на платежную форму.
	 *
	 * Одновременно добавляет запись в БД (только при режиме "internal_db")
	 * @param string $type тип формы. Возможные варианты:
	 * redirect - генерируется JS-код, при выводе которого в браузер происходит редирект на страницу формы с необходимыми параметрами.
	 * url - генерируется ссылка на форму, которую можно вставить в параметр href.
	 * @param string $summ сумма в валюте ценника. До 2-х знаков после запятой.
	 * @param string $user_email email адрес покупателя
	 * @return bool|string возвращает false в случае ошибки. Ошибку записывает в лог-файл.
	 */
	public function get_form($type = 'redirect', $summ, $user_email)
	{
		$date = date('d:m:Y H:i');
        $order_id = $this->db->insertOrder($summ, $this->currency, $user_email, $date);
        if (!$order_id) {
            $this->err('Error DB insert: ' . $this->db->lastErrorMsg());
            return false;
        }
    	$md5summ = $this->to_float($summ);
        $md5check = $this->makeMD5($summ, $this->currency, $order_id);
		$price_final = ($this->price_final) ? "&price_final=true" : "";
		$url = "http://secure.onpay.ru/pay/{$this->userform}?pay_mode=fix".
			"&pay_for={$order_id}" .
			"&price={$md5summ}" .
			"&ticker={$this->currency}" .
			"&convert={$this->convert}" .
			"&md5={$md5check}" .
			"&user_email=".urlencode($user_email) .
			"&f={$this->form_num}";
		if(!empty($this->ln)) {
			$url .= "&ln=".$this->ln;
		}

        $url .= $price_final;

		if(!empty($this->url_success)) {
			$url .= "&url_success=".urlencode($this->url_success);
		}
		if(!empty($this->url_fail)) {
			$url .= "&url_fail=".urlencode($this->url_fail);
		}
		$this->dbg($url);
		switch ($type) {
			case 'redirect':
				$result = "<script type='text/javascript'>window.location = '" . $url . "'</script>";
                break;
			case 'url':
                $result = $url;
                break;
			default:
				$this->err('Не правильно задан параметр type в функции get_form()');
				$result = false;
		}
        if ($result){
            $this->db->saveOrderUrl($order_id, $result);
        }
        return $result;
	}

	/**
	 * Последний записанный ID в БД в текущей сессии.
	 * @return int последний записанный ID
	 */
	public function get_last_order()
	{
		return $this->db->lastInsertRowID();
	}

	/**
	 * Валидирует запросы check или pay от Onpay.
	 *
	 * Ошибки записывает в лог-файл.
	 * Используется внутри класса, но возможно пользовательское использование.
	 * @param array $request get или post запрос ($_REQUEST).
	 * @return bool|string возвращает список ошибок или false, если ошибки отсутствуют.
	 */
	public function check_errors($type, $request)
	{
		$this->error = '';
        switch ($type) {
            case 'check':
                if (empty($request['order_amount'])) $this->err('В запросе check отсутствует параметр order_amount');
                if (empty($request['amount'])) $this->err('В запросе check отсутствует параметр amount');
                if (empty($request['order_currency'])) $this->err('В запросе check отсутствует параметр order_currency');
                if (empty($request['md5'])) $this->err('В запросе check отсутствует параметр md5');
                if (empty($request['pay_for'])) $this->err('В запросе check отсутствует параметр pay_for');
                break;
            case 'pay':
                if (empty($request['pay_for'])) $this->err('В запросе pay отсутствует параметр pay_for');
                if (empty($request['order_amount'])) $this->err('В запросе pay отсутствует параметр order_amount');
                if (empty($request['paymentDateTime'])) $this->err('В запросе pay отсутствует параметр paymentDateTime');
                if (empty($request['paid_amount'])) $this->err('В запросе pay отсутствует параметр paid_amount');
                if (empty($request['balance_currency'])) $this->err('В запросе pay отсутствует параметр balance_currency');
                if (empty($request['order_currency'])) $this->err('В запросе pay отсутствует параметр order_currency');
                if (empty($request['amount'])) $this->err('В запросе pay отсутствует параметр amount');
                if (empty($request['balance_amount'])) $this->err('В запросе pay отсутствует параметр balance_amount');
                if (empty($request['md5'])) $this->err('В запросе pay отсутствует параметр md5');
                if (empty($request['onpay_id'])) $this->err('В запросе pay отсутствует параметр onpay_id');
                break;
            default:
                $this->err('Неверный ответ сервера. Запрос не содержит параметров check или pay');
                break;
        }
    	return empty($this->error)?0:2;
	}

    protected function makeMD5_ask($data){
        switch(trim($data['type'])) {
            case 'pay':
                $str4md5 = "pay;{$data['pay_for']};{$data['onpay_id']};{$data['order_amount']};{$data['order_currency']};" . $this->key;
                break;
            case 'check':
                $str4md5 = "check;{$data['pay_for']};{$data['order_amount']};{$data['order_currency']};" . $this->key;
                break;
            default:
                $str4md5 = '';
                break;
        }
        $res =strtoupper(md5($str4md5));
        return $res;
    }

    protected function makeMD5_result($data){
        switch(trim($data['type'])) {
            case 'pay':
                $str4md5 = "pay;{$data['pay_for']};{$data['onpay_id']};{$data['pay_for']};{$data['order_amount']};{$data['order_currency']};{$data['code']};" . $this->key;
                break;
            case 'check':
                $str4md5 = "check;{$data['pay_for']};{$data['order_amount']};{$data['order_currency']};{$data['code']};".$this->key;
                break;
            default:
                $str4md5 = '';
                break;
        }
        $res =strtoupper(md5($str4md5));
        return $res;
    }

	/**
	 * Генерирует корректный XML-ответ для onpay включая цифровую подпись.
	 *
	 * Используется внутри класса, но возможно пользовательское использование.
	 * Пример использования:
	 * <code>
	 * $opy = new Onpay('internal_db', 'petyastore', 'ajsdansdbascasc');
	 * $data = array('type' => 'check', 'code' => 0, 'pay_for' => 2, 'comment' => 'Это текстовый комментарий', 'order_amount' => 20, 'order_currency' => 'RUR');
	 * echo $opy->gen_xml_answer($data);
	 * </code>
	 * Где:
	 * type - ответ на какой запрос генерировать - check или pay;
	 * code - код возврата:
	 * 0 ОК – означает, что “уведомление о платеже принято” если тип запроса был “pay” или “может быть принято” если тип запроса был “check”;
	 *
	 * pay_for - ID Клиента или заказа в системе Мерчанта для которых производится этот платеж;
	 * comment - Заметка, включённая в платёжную форму в системе Мерчанта. Будет доступна в списке платежей в интерфейсе Мерчанта;
	 * order_amount - Сумма платежа как в атрибуте “price” платёжной ссылки;
	 * order_currency - Валюта, как в атрибуте “currency” платёжной ссылки;
	 * key - ключ API IN.
	 *
	 * @param array $data массив с данными.
	 * @return string ответ в формате XML
	 */
	public function gen_xml_answer($data)
	{
		$ret = "";
        $md5 = $this->makeMD5_result($data);
        switch(trim($data['type'])) {
			case 'pay':
                $ret = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<result>
<code>{$data['code']}</code>
<comment>{$data['comment']}</comment>
<onpay_id>{$data['onpay_id']}</onpay_id>
<pay_for>{$data['pay_for']}</pay_for>
<order_id>{$data['pay_for']}</order_id>
<md5>$md5</md5>
</result>"; 
				break;
			case 'check':
				$ret = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<result>
<code>{$data['code']}</code>
<pay_for>{$data['pay_for']}</pay_for>
<comment>{$data['comment']}</comment>
<md5>$md5</md5>
</result>";
				break;
		}
		return $ret;
	}

	/**
	 * Функция-процессор onpay.
	 *
	 * Обрабатывает запрос и выполняет необходимые действия по смене статуса заказа
	 * TODO Необходима проработка для возможности использования с объектом БД MySql
	 * @param array $request
	 * @return bool|string возвращает false ghb ytelfxt
	 */
	public function process_onpay($request)
	{
		$type = trim($request['type']);
		$order_id = intval($request['pay_for']);
		$request['code'] = 0;
		$request['comment'] = 'OK';

        $err = $this->check_errors($type, $request);
        if ($err==0) {
            $order = $this->db->findOrder($order_id);
            if ($order) {
                if ( (($type=='pay'  ) and (floatval($request['amount'])< floatval($order['amount'])))){
                    $this->err('Неверная сумма платежа');
                    $err =3;
                }
                elseif ( (($type=='check') and (floatval($request['amount'])>floatval($order['amount']))) ){
                    $this->err('Переплата');
                    $err =3;
                }
                elseif ( (($type=='check') and (floatval($request['amount'])<floatval($order['amount']))) ){
                    $this->err('Недоплата');
                    $err =2;
                }
                elseif ($request['order_currency']!=$order['currency']){
                    $this->err('Неверная валюта платежа');
                    $err =3;
                }
                elseif ($this->makeMD5_ask($request)!=$request['md5']){
                    $this->err('Неправильный md5');
                    $err =7;
                }
                else {
                    echo $this->gen_xml_answer($request);
                    if ($type == 'pay') $this->set_payed_status($request);
                    return true;
                }
            }
            else {
                $this->err('Платеж не найден');
                $err =2;
            }
        }
        $this->generate_error($err, $request);
        return false;
	}


	/**
	 * Функция генерирует корректный XML с ошибкой.
	 *
	 * 2 Только для запросов типа “check” Платёж отклонён. В этом случае OnPay не примет платёж от Клиента;
	 * 3 Ошибка в параметрах. OnPay не будет пытаться повторно послать это уведомление в API мерчанта и отметит этот платёж статусом “уведомление не доставлено в API” если тип запроса “pay”. Если тип запроса “check” – OnPay не примет этот платеж;
	 * 7 Ошибка авторизации. MD5 подпись неверна;
	 * 10 Временная ошибка. OnPay попробует повторно послать это уведомление несколько раз в течение следующих 72 часов после чего пометит платёж статусом “уведомление не доставлено в API”);
	 *
	 * @param string $num номер ошибки (2,3 или 7)
	 * @param array $data данные запроса
	 */
	public function generate_error($num, $data)
	{
		switch ($num) {
			case '2':
				$err = "bad_pay";//"Error 2. Payment declined.";
				break;
			case '3':
				$err = "bad_pay";//"Error 3. Error in the parameters.";
				break;
			case '7':
				$err = "bad_pay";//"Error 7. Authorization error. MD5 signature is incorrect.";
				break;
			default:
				$err = "";
		}
		$data['code'] = $num;
		$data['comment'] = $err;
		echo $this->gen_xml_answer($data);
        exit;
	}


	private function set_payed_status($request)
	{
		$order_id = intval($request['pay_for']);
		$onpay_id = intval($request['onpay_id']);
		$payed_date = new \DateTime($request['paymentDateTime']);//DateTime()date('d:m:Y H:i');
    	if (!$this->db->updateOrder($order_id, $onpay_id, $payed_date)) {
            $this->err('Error DB update: ' . $this->db->lastErrorMsg());
            return false;
        }
        return true;

	}

	private function get_payed_status($order_id)
	{
		return $this->db->orderStatus($order_id);
	}

	/**
	 * Возвращает корректный URL до скрипта, в котором запрашивается функция.
	 *
	 * (Может применяться для вывода URL API IN мерчанта, если функция callback находится в том-же скрипте,
	 * что и вызывающая функцию строка скрипта)
	 * @return string
	 */
	public function get_this_url()
	{
		return $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	}


	/**
	 * Округляет/преобразует строковое значение $sum в тип float с 2-я знаками после запятой.
	 *
	 * @param string $sum
	 * @return float
	 */
	public function to_float($sum)
	{
		$sum = round(floatval($sum), 2);
		$sum = sprintf('%01.2f', $sum);
		
		if (substr($sum, -1) == '0') {
			$sum = sprintf('%01.1f', $sum);
		}
		
		return $sum;
	}

	public function err($message)
	{
		$this->error .= $message . '<br/>';
	}

	public function dbg($message)
	{
		if($this->debug) $this->log .= $message . '<br/>';
	}
}