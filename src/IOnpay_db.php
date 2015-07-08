<?php
	
namespace Fau\Onpay;

/**
 * Интерфейс класса базы данных для взаимодействия с API Onpay
 *
 * @author fau
 * @version 1.0
 * @filesource
 * @namespace Onpay
 *
 */
interface IOnpay_db
{
    public function __construct($dbname);
    public function init();
    public function lastErrorMsg();
    public function insertOrder($summ, $user_email, $date);
    public function lastInsertRowID();
    public function updateOrder($order_id, $onpay_id, $payed_date);
    public function orderStatus($order_id);
    public function findOrder($order_id);
}
