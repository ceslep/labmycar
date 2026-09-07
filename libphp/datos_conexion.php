<?php
            header("Access-Control-Allow-Origin: *");
            ini_set('error_reporting', E_ALL);		
			$host="localhost";
            $user="iedeocci_mycar";
		  	$pass="Qpzm894035*";
		  	$database="iedeocci_mycar"; 
            sleep(0);
            $datos=json_decode(file_get_contents("php://input"));
            $mysqli=new mysqli($host, $user, $pass , $database);

            function SQIO($mysqli,$sql){
                echo json_encode(array("msg"=>false,"sql"=>str_replace("\r\n","",$sql),"error"=>$mysqli->error));
                exit(0);
            }
