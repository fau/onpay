<?php

/**
 * Класс взаимодействия с DB SQLLite3 для API Onpay
 *
 * @author fau
 * @version 1.0
 * @filesource
 * @namespace Onpay
 *
 */


class onpay_db_sqllite3 extends ionepay_db
{

    var $db;

    public function __construct($dbname){
        if (!isset($dbname)) $dbname = 'onpay.db';
        $this->db = new SQLite3(__DIR__ . '/' . $dbname);
    }

    public function init(){
        $result = $this->db->exec('
					CREATE TABLE IF NOT EXISTS "orders" (
			            "id" integer PRIMARY KEY AUTOINCREMENT,
						"summ" text null,
						"onpay_id" text null,
						"user_phone" text null,
						"user_email" text null,
						"create_date" text,
						"payed_date" text,
						"payed" BOOLEAN DEFAULT "0" NULL);
				');
        return $result;
    }

    public function lastErrorMsg(){
        return $this->db->lastErrorMsg();
    }

    public function insertOrder($summ, $user_email, $date){
        return $this->db->exec(
            "INSERT INTO 'orders' ('summ', 'user_email', 'create_date', 'payed')".
            " VALUES ('$summ', '$user_email', '$date', '0')"
        );
    }

    public function lastInsertRowID(){
        return $this->db->lastInsertRowID();
    }

    public function updateOrder($order_id, $onpay_id, $payed_date){
        return $this->db->exec("UPDATE orders SET onpay_id = '$onpay_id', payed_date = '$payed_date', payed = 1 WHERE id = $order_id");
    }

    public function orderStatus($order_id){
        return $this->db->querySingle("SELECT payed FROM orders WHERE id = $order_id");
    }

    public function findOrder($order_id, $summ){
        $sql = "SELECT * FROM orders WHERE id = '$order_id' and payed=0 ";
        if ($summ) $sql .= " and summ<=".floatval($summ);
        return null!=($this->db->querySingle( $sql));
    }
}
