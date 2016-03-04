<?php
	require('suning/SuningSdk.php');
	require('suning/request/transaction/OrderdeliveryAddRquest.php');
	header("content-type;text/html;charset=utf8");
	echo "<meta http-equiv='Content-Type' content='text/html'; charset='utf8'>";
	set_time_limit(0);
	//数据库
    $SqlServerName 	= '192.168.1.201';
    $SqlUser 		= 'liaoyc';
    $SqlPasswd		= 'liaoyc123';
    $SqlDbName		= '54sport_center';
	
/* 	$SqlServerName 	= '127.0.0.1';
	$SqlUser 		= 'root';
	$SqlPasswd		= '';
	$SqlDbName		= 'center'; */
	//APP参数
	$OrderStartTime = '2014-11-09 00:00:00';
	$OrderEndTime 	= date("Y-m-d H:i:s",time());
	

	$serverUrl = "http://open.suning.com/api/http/sopRequest";
	$appKey = "ddd3a7465f5153aa1a061387012a92f5";
	$appSecret = "d436f8ce551f363e02caf0c932f9c54e";
	//物流编号
	$shipCode = array(
					"E01"=>"EMS标件",
					"E01"=>"EMS经济件",
					"S02"=>"顺丰速运",
					"Y01"=>"圆通速递",
					);
	
	$client = new DefaultSuningClient($serverUrl,$appKey,$appSecret,'json');
	$conn = mysql_connect($SqlServerName,$SqlUser,$SqlPasswd);
	if (!$conn)
	{
		die('Could not connect: ' . mysql_error());
	}
	
	mysql_select_db($SqlDbName);
	mysql_query("set names utf8");
	
	
/* 	$req = new OrdQueryRequest(); 
	$req -> setStartTime("2014-11-06 00:00:00");
	$req -> setEndTime("2014-11-07 00:00:00");
	$req -> setOrderLineStatus("10");
	$req -> setPageNo("1");
	$req -> setPageSize("2");
	$resp = $client -> execute($req);
	print_r("返回响应报文:".$resp);die(); */
	
	//上传订单
/* 	$req = new OrderdeliveryAddRquest(); 
	$sql = "select d.logi_no,d.logi_name,d.outer_order_id,d.order_id from sdb_delivery as d where d.logi_no<>'' and outer_order_id in(select o.outer_order_id from sdb_orders as o where o.is_anti<>'true' and o.terminal_tag='SUNING_1')";
	$updateData = select($sql);
	//var_dump($updateData);
	//var_dump($shipCode);
	$findError = array();
	foreach($updateData as $dataTemp)
	{
		$CompanyCode = "";
		foreach($shipCode as $shipName => $ship)
		{
			if(trim($dataTemp["logi_name"]) == trim($ship))
			{
				$CompanyCode = $shipName;
			}
		}
		if($CompanyCode == "")
		{
			$err = array();
			$err["shipname"] = $dataTemp["logi_name"];
			$err["outerid"]	 = $dataTemp["outer_order_id"];
			$err["msg"]	 	 = "Can't find shipName";
			array_push($findError,$err);
			continue;
		}
		$req -> setOrderCode($dataTemp["outer_order_id"]);
		$req -> setExpressNo($dataTemp["logi_no"]);
		$req -> setExpressCompanyCode($CompanyCode);
		$req -> setDeliveryTime(date("Y-m-d H:i:s",time()));
		
		$sql = "select minfo from sdb_order_items where order_id={$dataTemp["order_id"]}";
		$minfo = select($sql);
		$productCode = array();
		foreach($minfo as $one)
		{
			array_push($productCode,$one["minfo"]);
		}
		$sendDetail = new SendDetail();
		$sendDetail -> setProductCode ($productCode);
		$req -> setSendDetail($sendDetail);
		$resp = $client -> execute($req);
		$ver = json_decode($resp,TRUE);
		var_dump($productCode);
		var_dump($ver);die();
		if(isset($ver["sn_responseContent"]["sn_error"]["error_code"]))
		{
			$err = array();
			$err["shipname"] = $dataTemp["logi_name"];
			$err["outerid"]	 = $dataTemp["outer_order_id"];
			$err["msg"]	 	 = $ver["sn_responseContent"]["sn_error"]["error_code"];
			array_push($findError,$err);
			continue;
		}
		SqlUpdate("sdb_delivery",array("is_anti"=>"1"),"delivery_id='{$dataTemp["delivery_id"]}'");
	}
	echo "上传错误条数".count($findError) ."<br />";
	foreach($findError as $errNum => $errone)
	{
		echo "{$errNum}.物流名称:{$errNum["shipname"]}  外部订单号:{$errone["outerid"]}   错误信息:{$errone["msg"]}<br />";
	}  */
	
	
	//下载订单
	$req = new OrdercodeQueryRequest(); 
	$OrderStartTimeData = select("select last_download_time from sdb_terminal where system='SUNING'");
	$OrderStartTime = date("Y-m-d H:i:s",$OrderStartTimeData[0]["last_download_time"]);
	echo "本次更新范围: {$OrderStartTime} 到 {$OrderEndTime}<br />";
	$req -> setStartTime($OrderStartTime);
	$req -> setEndTime($OrderEndTime);
	//订单状态 10待发货，20已发货，21部分发货，30交易成功 ，40交易关闭
	$req -> setOrderStatus("10");
	$resp = $client -> execute($req);
	$data = json_decode($resp,TRUE);
	$updateCount = 0;
    if(!isset($data["sn_responseContent"]["sn_body"]))
    {
        echo "此时间段内无订单<br />";
        exit();

    }
	foreach($data["sn_responseContent"]["sn_body"]["orderCodeQuery"]["orderCode"] as $orderCode)
	{
		$order = new OrderGetRequest(); 
		$order -> setOrderCode($orderCode);
		$orderData = $client->execute($order);
		$orderData = json_decode($orderData);
		$orderData = $orderData->sn_responseContent->sn_body->orderGet;
		$orderTemp = array();
		$orderTemp["outer_order_id"] 	= $orderData->orderCode;
		$orderTemp["outer_buyer"] 		= $orderData->userName;
		$orderTemp["acttime"] 			= time(); 
		$orderTemp["mark_text"] 		= $orderData->sellerOrdRemark;
		$orderTemp["memo"] 				= $orderData->buyerOrdRemark;
		$orderTemp["last_change_time"] 	= time();
		$orderTemp["createtime"] 		= strtotime($orderData->orderSaleTime);
		$orderTemp["ship_name"] 		= $orderData->customerName;
		$zoneId 						= area_info($orderData->cityName,$orderData->districtName);
		$orderTemp["ship_area"] 		= "mainland:" . $orderData->provinceName . "/".$orderData->cityName . "/" .$orderData->districtName.":{$zoneId}";
		$orderTemp["ship_addr"] 		= $orderData->customerAddress;
		$orderTemp["ship_mobile"] 		= $orderData->mobNum;
		$orderTemp["pay_status"]  		= "1";
		$orderTemp["terminal_tag"]  	= "SUNING_1";
		$orderTemp["terminal_name"]		= "苏宁匹克官方旗舰店";
		$orderTemp["group_name"]  		= "苏宁匹克旗舰店确认小组";
		$orderTemp["group_id"]			= "24";
		$orderTemp["payed"]				= 0;
        $orderTemp["order_id"]			= gen_id();
		$count = select("select count(order_id) as count from sdb_orders where outer_order_id='{$orderCode}'");
		if($count[0]["count"] != "0")
		{
			continue;
		}
        $orderId = SqlInsert($orderTemp,"sdb_orders","order_id");
		
		foreach ($orderData->orderDetail as $orderItem)
		{
			$itemTemp = array();
			$itemTemp["order_id"] 		= $orderTemp["order_id"];
			$itemTemp["name"] 			= $orderItem->productName;
			$itemTemp["nums"] 			= intval($orderItem->saleNum);
			$itemTemp["bn"] 			= $orderItem->itemCode;
			$itemTemp["amount"]	  		= $orderItem->payAmount;
			$itemTemp["product_id"]		= "0";
			$itemTemp["terminal_name"]	= "苏宁匹克官方旗舰店";
			$itemTemp["terminal_tag"]  	= "SUNING_1";
			$orderTemp["payed"]			+= (int)$orderItem->payAmount;
			$itemTemp["price"]			= $orderItem->unitPrice;
			$itemTemp["minfo"]			= $orderItem->productCode;
			$itemTemp["outer_order_id"]	= $orderData->orderCode;

			SqlInsert($itemTemp,"sdb_order_items","item_id");
		}

		$updateCount++;
		//
	}
	SqlUpdate("sdb_orders",array("payed"=>$orderTemp["payed"]),"order_id='{$orderTemp["order_id"]}'");
	SqlUpdate("sdb_terminal",array("last_download_time"=>time() - 7200),"system='SUNING'");
	echo "更新 {$updateCount} 条 订单";
	
	function SqlInsert($data,$table,$KeyName)
	{	
		$typeStr = "";
		$valueStr = "";
		foreach ($data as $type => $value)
		{
			if(is_null($value) || $value == "")
			{
				continue;
			}
			$typeStr .= "{$type},";
			if(is_string($value))
			{
				$valueStr .= "'{$value}',";
			}
			else
			{
				$valueStr .= "{$value},";
			}
		}
		$typeStr = substr($typeStr,0,-1);
		$valueStr = substr($valueStr,0,-1);
		$insertSql = "INSERT INTO {$table}({$typeStr}) VALUES({$valueStr})";
        $result = mysql_query($insertSql);
		if($result)
		{
			return 0;
		}
		else
		{
            $sss = mysql_error();
			return -1;
		} 
	}
	
	function select($sql)
	{
		$data = mysql_query($sql);
        $ss = mysql_error();
		$allData = array();
		while($row = mysql_fetch_row($data,MYSQL_ASSOC))
		{
			array_push($allData,$row);
		}
		return $allData;
	}
	
	function SqlUpdate($table,$data,$where)
	{
		$setValue = "";
		foreach($data as $keyName => $value)
		{
			$setValue .= "{$keyName}='{$value}',";
		}
		$setValue = substr($setValue,0,-1);
		$sql = "update {$table} set {$setValue} where {$where}";
		$data = mysql_query($sql);
		return $data;
	}
	
	function gen_id()
	{
        $i = rand(0,9999);
        do{
            if(9999==$i){
                $i=0;
            }
            $i++;
            $order_id = date("YmdHis").str_pad($i,4,'0',STR_PAD_LEFT);
            $row = mysql_query('SELECT order_id from sdb_orders where order_id ='.$order_id);
        }while(!$row);
        return $order_id;
	}
	
	function area_info($city,$district){
        if($district == 0){
            $row_city = mysql_query("SELECT region_id FROM sdb_regions WHERE local_name='".$city."'");
            $ss= mysql_error();
			$row_city = mysql_fetch_row($row_city);
		   $region_id=$row_city[0]['region_id'];
            return $region_id;
        }else{
            $row_city =  mysql_query("SELECT region_id FROM sdb_regions WHERE local_name='".$city."'");
			$row_city = mysql_fetch_row($row_city,MYSQL_ASSOC);
            $row =  mysql_query("SELECT  region_id FROM sdb_regions WHERE local_name='".$district."' and p_region_id ='".$row_city['region_id']."'");
            $row = mysql_fetch_row($row,MYSQL_ASSOC);
			$region_id=$row['region_id'];
            return $region_id;
        }
    }
	function lockTable($tableName)
    {
        $sql = "LOCK TABLES {$tableName} WRITE";
        mysql_query($sql);
    }

    function unlockTable()
    {
        $sql = "UNLOCK TABLES";
        mysql_query($sql);
    }
?>