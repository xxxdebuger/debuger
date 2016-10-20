<?php

//引入必要的lib文件
//require_once '../../lib/t_init.inc';
require_once '../../lib/t_safe_check.inc';
require_once '../../lib/t_csrf_check.inc';
require_once 'checkIsLogin.inc';
re
require_once '../../lib/t_optlog.inc';
require_once '../../lib/t_sqlite3handler.inc';
require_once '../../lib/t_sysconfig.inc';
//require_once '../../lib/t_filepolicy.inc';
require_once '../../lib/t_html_view.inc';


class CHtml_CertUpdate_t extends CHtml_AppModul_t
{		
	protected 	$do_type;			//操作类型	
	protected 	$hand_msg;
	
	protected 	$_csrf;
	
	function __construct( ){
		parent::__construct();				
// 		if( !$this->is_admin )		
// 		{
// 			die("权限不足！");
// 		}
				
		$_desc = "本模块提供更换管理器IE证书的功能,需要用户提供以下三个文件<BR>";
		$_desc.= "1. 根证书（cacert.pem）：由第三方CA签发的根证书文件<BR>";
		$_desc.= "2. 设备证书文件（server.crt）：由根证书签发<BR>";
		$_desc.= "3. 设备证书私钥（server.key）：如果使用des3/des加密,则需要设置私钥密码<BR>";
		$_desc.= "<span style='color:red'></span><BR>";
		$this->description = $_desc;
		$this->debug_mode(true, true);
		$this->s_lab = array( 
				'TITLE'=>'证书管理', 
				'HINT'=>NULL,
				'BTNS'=>NULL,
				'DESC'=>$_desc );
		
		$this->do_type = tf_get_pvar( 'do_type' );	
		
		$this->mt_labs = array(
				'0' => array(
						'TITLE' => '更换IE证书',
						'MV' => '0',
						'BTNS' => NULL,
						'DESC' => $_desc
				),
				'1' => array(
						'TITLE' => '当前证书信息',
						'MV' => '1',
						'BTNS' =>NULL,
						'DESC' => '本模块显示当前设备证书信息')
				);
		$this->mt_pname = "mtp";
		$this->mt_pdval = "0";
		$this->page_style = 1;
		$this->hand_msg = NULL;
	}
	
	function __destruct(){
		parent::__destruct();
		UNSET($this->_csrf);
	}	
	

	
	/*
	* @name 	handle_data_after_body_started()
	* @desc 	在输出<BODY>之后的数据处理函数
	*/
	protected function handle_data_after_body_started()
    {
		//$this->trace_calling(__FUNCTION__ );
		$do_type = tf_get_pvar('do_type');
		$this->var_debug( $do_type, 'do_type' );
		
		$handed = TRUE;		
		if( $do_type == "check" )
		{
		    $res = $this->certs_upload_and_verify();
		    if($res != NULL)
		    {
	            tf_msg_box($res);
		    }
		    else 
		    {	
 		        tf_msg_box("证书链校验成功");
// 		        if(file_exists("/conf/mca.firstuse")){
//                 	unlink("/conf/mca.firstuse");
//                 }
//                   die("<script language=javascript>  parent.location='/pages/login/go_index.php';</script>");
//                   exit();
 //		        tf_msg_box("证书链校验成功");
 		        $tmpDir="/tmp/camssl/";
 		        exec("rm -rf ".$tmpDir."*", $out);
 		        exec("/bin/touch /conf/mca.firstuse");
 		        
 		        exec("/bin/touch /conf/mca.reboot_firstuse");
 		        exec("rm -rf /dev/shm/mca.reboot_kill");
		    } 
		   
		}
		else 
		{
		    return false;
		}
		$path="/conf/patchs/ssl_crt/";
		$server = $path."server.crt";
		$server_key = $path."server.key";
		$cacert = $path."cacert.pem";
		$capass = $path."apache_pass.sh";
		$capath = $path."CApath";
		$tmpDir="/tmp/camssl/";
		if((file_exists($server) &&
				file_exists($server_key) &&
				file_exists($cacert)&&
				file_exists($capass)))
		{
			if(file_exists("/conf/mca.firstuse")){
				unlink("/conf/mca.firstuse");
			}
			die("<script language=javascript>  parent.location='/pages/login/go_index.php';</script>");
		//	die("<script language=javascript> self.location='/pages/login/go_index.php';</script>");
			exit();
		}
		
		//$this->trace_leaving( __FUNCTION__  );
	}
	
	
	/**
	 * @name 	certs_upload_and_verify()
	 * @desc 	证书上传、并校验证书链和证书有效性，成功返回NULL，否则返回错误消息
	 */
	protected function certs_upload_and_verify()
	{
	    $desc = "";
	    $handed = TRUE;
	    $final_path = "/conf/patchs/ssl_crt/";
	    exec( "/bin/mkdir -p ".$final_path );
	    $final_path2 = "/conf/patchs/old_ssl_crt/";
	    exec( "/bin/mkdir -p ".$final_path2 );
	    $tmpDir="/tmp/camssl/";
	    exec( "/bin/mkdir -p ".$tmpDir );
	    $cert_file = "";
	
	
	    $desc .= "上传证书,";
	    foreach ($_FILES["certs"]["error"] as $key => $error)
	    {
	         
	
	        switch ($key) {
	            case 0:
	                $cert_file = "cacert.pem";
	                break;
	            case 1:
	                $cert_file = "server.crt";
	                break;
	            case 2:
	                $cert_file = "server.key";
	                break;
	            default:
	                break;
	        }
	
	        if( empty($_FILES["certs"]["name"][$key]) )
	        {
	            $this->unlink_file($_FILES["certs"]["tmp_name"]);
	            $desc = "上传证书文件为空,请选择正确的".$cert_file."文件";
	            $handed = false;
	            break;
	        }
	         
	        if(strcmp($cert_file, $_FILES["certs"]["name"][$key]))
	        {
	            $this->unlink_file($_FILES["certs"]["tmp_name"]);
	            $desc = "文件".$cert_file."选择错误,请重新选择";
	            $handed = false;
	            break;
	        }
	         
	        if ($error == UPLOAD_ERR_OK)
	        {
	            $tmp_name = $_FILES["certs"]["tmp_name"][$key];
	            move_uploaded_file($tmp_name, $tmpDir.$cert_file);
	            @unlink($tmp_name);
	        }
	        else
	        {
	            $this->unlink_file($_FILES["certs"]["tmp_name"]);
	            $desc = "传输文件".$cert_file."过程出错";
	            $handed = false;
	            break;
	        }
	
	    }
	     
	    if(!$handed)
	    {
	        return $desc;
	    }
	     
	
	    $desc = "验证证书,";
	    $password = trim($_POST["capass"]);
	    $radio = trim($_POST["edp_upass_radio"]);
	     
	    if(NULL == ($desc = $this->check_cert_file($tmpDir, $password)))
	    {
	         
	        if(empty($password))
	        {
	            $password = "-";
	        }
	       exec("mv /conf/patchs/ssl_crt/  /conf/patchs/old_ssl_crt/");
	       $final_path = "/conf/patchs/ssl_crt/";
	       exec( "/bin/mkdir -p ".$final_path );
	        //生成capass文件并将所有文件移动到最终目录
	        $ret = file_put_contents($final_path."apache_pass.sh",  "#!/bin/sh\necho \"$password\"\n");
	        exec("chmod 777 ".$final_path."apache_pass.sh");
	        if(!$ret)
	        {
	            $this->log_desc .= "创建capass文件失败";
	            $handed = false;
	        }
	
	        copy($tmpDir."cacert.pem", $final_path."cacert.pem");
	        copy($tmpDir."server.crt", $final_path."server.crt");
	        copy($tmpDir."server.key", $final_path."server.key");
	        exec("rm -rf ".$tmpDir."*", $out);
	    }
	    else
	    {
	        exec("rm -rf ".$tmpDir."*", $out);
	        $handed = false;
	    }
	
	
	    if( $handed)
	    {
	        return NULL;
	    }
	    else
	    {
	        return $desc;
	    }
	
	}
	
	
	
	/*
	 * @name 	check_cert_file($path, $password)
	 * @desc 	证书文件有效性检查,有效返回true,否则返回false,并清空路径下的证书文件
	 */
	protected function check_cert_file($path, $password="", $desc="")
	{
	    
	    $server = $path."server.crt";
	    $server_key = $path."server.key";
	    $cacert = $path."cacert.pem";
	    $capass = $path."apache_pass.sh";
	    $capath = $path."CApath";
	    $final_path = "/conf/patchs/ssl_crt/";
	    
	    $password = empty($password)?"":$password;
	     
	    //如果证书文件之一不存在
	    if(!(is_file($server) &&
	        is_file($server_key) &&
	        is_file($cacert)))
	    {
	        @unlink($server);
	        @unlink($server_key);
	        @unlink($cacert);
	         
	        $desc .= "证书不完整.";
	        return $desc;
	    }
	     
	    //验证根证书与设备证书证书链
	    $cmd_chain1 = "mkdir -p $capath 2>&1";
	    unset($_out);
	    exec( $cmd_chain1, $_out);
	    if(!empty($_out))
	    {
	        $desc .= "创建临时路径失败.";
	        return $desc;
	    }
	     
	    $cmd_chain2 = "/copsec/soft/openssl/bin/openssl x509 -hash -in $cacert -noout";
	    unset($_out);
	    exec( $cmd_chain2, $_out);
	    if(8 != strlen($_out[0]))
	    {
	        $desc .= "根证书的hash值计算失败,";
	        return $desc;
	    }
	    
	    $cmd_chain3 = "cp $cacert $capath/$_out[0].0";
	    unset($_out);
	    exec( $cmd_chain3, $_out);
	    $expect = "/tmp/camssl/server.crt: OK";
	    $cmd_chain4 = "/copsec/soft/openssl/bin/openssl verify -CApath $capath $server 2>&1";
	    unset($_out);
	    exec( $cmd_chain4, $_out);
	    if(strcmp($_out[0], $expect))
	    {
	        
	        $desc .= "证书链认证失败,错误信息:$_out[1].";
	        return $desc;
	    }
	    
	    // 验证设备私钥密码是否正确
	    $cmd_private_key_password = "openssl rsa -in $server_key -passin pass:$password -out $path/server_tmp.key 2>&1";
	    unset($_out);
	    unset($expect);
	    $expect = "writing RSA key";
	    exec( $cmd_private_key_password, $_out);
	    if(strcmp($_out[0], $expect))
	    {
	        $desc .= "证书私钥密码验证失败,错误信息:$_out[1].";
	        return $desc;
	    }
// 	    else
// 	    {
// 	    	$myfile = fopen("/opt/soft/copsec/soft/apache/conf/ssl.crt/apache_pass.sh", "w") or die("Unable to open file!");
// 	    	$txt = "#!/bin/sh\necho \"$password\"\n";
// 	    	fwrite($myfile, $txt);
// 	    	fclose($myfile);
// 	    }
	
	    //验证设备证书和设备私钥是否匹配
	    $cmd_private_key = "/copsec/soft/openssl/bin/openssl pkcs12 -export -clcerts -in $server -inkey $server_key  -passout pass:123456 -passin pass:$password -out check_tmp.p12 2>&1";
	    unset($_out);
	    exec( $cmd_private_key, $_out);
	    if(!empty($_out[0]))
	    {
	        $desc .= "设备证书和设备私钥匹配失败,错误信息:$_out[0].";
	        return $desc;
	    }
	    
	    //验证证书时间
	    $cmd_start = "cat $server | grep \"Not Before\" 2>&1";
	    $cmd_end = "cat $server | grep \"Not After\" 2>&1";
	    exec($cmd_start, $out_start);
	    if(empty($out_start[0]))
	    {
	        $desc .= "提取证书中的日期失败.";
	        return $desc;
	    }
	    exec($cmd_end, $out_end);
	    if(empty($out_end[0]))
	    {
	        $desc .= "提取证书中的日期失败.";
	        return $desc;
	    }
	    
	    $start=explode(": ", $out_start[0]);
	    $end=explode(": ", $out_end[0]);
	    $start = strtotime($start[1]);
	    $end = strtotime($end[1]);
	    $local =  time();
	    if($end-$start < 7*24*60*60)
	    {
	        $desc .= "证书的有效期小于7天，证书不合法.";
	        return $desc;
	    }
	    
	    if($local > $end || $local<$start)
	    {
	        $desc .= "设备时间不在证书有效期范围内，证书不合法.";
	        return $desc;
	    }
	    
	    return NULL;
	}
	
	
	//删除临时文件
	protected function unlink_file($files)
	{
	    $tmpPath = "/tmp/camssl/";
	    
	    if(!is_array($files))
	    {
	        return false;
	    }
	    
	    foreach ($files as $file)
	    {
	        @unlink($file);
	    }
	    
	    exec("/bin/rm -rf ".$tmpPath."*", $out);
	}
	


		

	/**
	 * @name 	echo_mycss_codes_in_head()
	 * @desc	向页面输出css样式表代码
	 */
// 	protected function echo_mycss_codes_in_head()
// 	{
// 		$this->trace_calling(__FUNCTION__ );	
// 		$this->trace_leaving( __FUNCTION__  );
// 	}
	
	
	/**
	 * @name 	echo_myjs_codes_in_head()
	 * @desc 	动态写入js代码
	 */
	protected function echo_myjs_codes_in_head()
	{
		//$this->trace_calling(__FUNCTION__ );
	    tf_put_line("<script type=\"text/javascript\" src=\"js/jquery-1.6.min.js\"></script>");
		tf_put_line( "<script language='javascript'>" );
 		tf_put_line( "$(document).ready(function() " );
 		tf_put_line( "{" );
 		tf_put_line( "$(\"#pass_td\").hide();" ); 		
 		tf_put_line( "});" );
 		
		tf_put_line( "function check_cert( dotype )" );
		tf_put_line( "{" );
		tf_put_line( "    $(\"#do_type\").val(dotype);" );
		tf_put_line( "    $(\"#form_vu\").submit();" );
		tf_put_line( "    return true;" );
		tf_put_line( "}" );
		
		tf_put_line( "function hidden_tr()" );	
		tf_put_line( "{" );
		tf_put_line( " var pass = $(\"input[name=edp_upass_radio]:checked\").val();" );
		tf_put_line( "if(pass == \"yes\")" );
		tf_put_line( "{" );
		tf_put_line( " $(\"#pass_td\").show();" );
		tf_put_line( "}" );	
		tf_put_line( " else if(pass == \"no\")" );
		tf_put_line( "{" );
		tf_put_line( "$(\"#pass_td\").hide();" );
		tf_put_line( "}" );
		tf_put_line( "return true;" );
		tf_put_line( "}" );
		tf_put_line( "</script>" );	
	//	$this->trace_leaving( __FUNCTION__  );
	}	
	
	protected function echo_cert_update_forms()
	{
	//	$this->trace_calling(__FUNCTION__ );

		tf_put_line( "<div class=\"core\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" align=\"center\" >" );	
		//添加或修改表单
		tf_start_form("form_vu", tf_self(), 'POST', NULL, "multipart/form-data" );
		tf_input_hidden('do_type', 'uploading');
		
		tf_put_line( "<div class=\"core\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" align=\"center\">" );
		tf_put_line( "<fieldset style=\"width:80%;font-size: 12px;font-family:'verdans';\">" );
		tf_put_line( "<legend>上传第三方证书</legend>" );
		tf_put_line( "<br>" );
		tf_put_line( "<table style=\"width:95%;font-size: 12px;font-family:'verdans';\" id=\"form_table\" cellspacing=\"15px\" cellpadding=\"0\">" );
		
		tf_put_line( "<tr >" );
		tf_put_line( "<td width='40%' align='right' id='tdt_logtype'>选择根证书(cacert.pem)&nbsp;:</td>" );
		tf_put_line( "<td width='60%' align='left' >&nbsp;");
		tf_put_line( "<input id=\"certs\" name=\"certs[]\" type=\"file\" size=\"50\">" );
		tf_put_line( "</td>" );
		tf_put_line( "</tr>" );
		tf_put_line( "<tr >" );
		tf_put_line( "<td width='40%' align='right' id='tdt_logtype'>选择设备证书(server.crt)&nbsp;:</td>" );
		tf_put_line( "<td width='60%' align='left' >&nbsp;");
		tf_put_line( "<input id=\"certs\" name=\"certs[]\" type=\"file\" size=\"50\"> " );
		tf_put_line( "</td>" );
		tf_put_line( "</tr>" );
		
		tf_put_line( "<tr >" );
		tf_put_line( "<td width='40%' align='right' id='tdt_logtype'>选择私钥文件(server.key)&nbsp;:</td>" );
		tf_put_line( "<td width='60%' align='left' >&nbsp;");
		tf_put_line( "<input id=\"certs\" name=\"certs[]\" type=\"file\" size=\"50\">" );
		tf_put_line( "</td>" );
		tf_put_line( "</tr>" );
		tf_put_line( "<tr >" );
		tf_put_line( "<td width='40%' align='right'>&nbsp是否使用私钥密码:");
		tf_put_line( "</td>" );
		tf_put_line( "<td width='60%' align='left' id='tdt_logtype'>" );
		tf_put_line( "<input type=\"radio\" id=\"edp_upass_radio\" name=\"edp_upass_radio\" value=\"yes\" onclick=\"hidden_tr()\">设置私钥密码&nbsp;&nbsp;" );
		tf_put_line( "<input type=\"radio\" id=\"edp_upass_radio\" name=\"edp_upass_radio\" value=\"no\" checked onclick=\"hidden_tr()\">不设置私钥密码&nbsp;&nbsp;" );
		tf_put_line( "</td>" );
		tf_put_line( "</tr>" );
		tf_put_line( "<tr id='pass_td'>" );
		tf_put_line( "<td width='40%' align='right'>&nbsp;设置私钥密码:");
		tf_put_line( "</td>" );
		tf_put_line( "<td width='60%' align='left'>&nbsp;");
		tf_put_line( "<input id=\"capass\" name=\"capass\" type=\"password\" size=\"50\">" );
		tf_put_line( "</td>" );
		tf_put_line( "</tr>" );
		tf_put_line( "<tr >" );
		tf_put_line( "<td width='100%' align='center' valign='center' colspan='2'>&nbsp;");
		tf_button( 'btn_submit', "证书链有效性验证", false, "onclick=check_cert('check')" );
		tf_put_line( "</td>" );
		tf_put_line( "</tr>" );
		
		tf_put_line( "</table>" );
		tf_put_line( "<br>" );
		
		tf_put_line( "</fieldset>" );

		tf_end_form();
	}
	
	protected function echo_current_cert_info()
	{
	//	$this->trace_calling(__FUNCTION__ );
		
		tf_put_line( "<div class=\"core\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" align=\"center\">" );
		tf_put_line( "<fieldset style=\"width:80%;font-size: 12px;font-family:'verdans';\">" );
		tf_put_line( "<legend>当前设备证书信息</legend>" );		
		tf_put_line( "<table style=\"width:95%;font-size: 12px;font-family:'verdans';\" id=\"form_table\">" );			
		tf_put_line( "<tr >" );
		tf_put_line( "<td width='100%' align='left' valign='top'><span>");
		
		tf_html_line(NULL);

		
		$res = $this->get_current_cert_info();
		
		if(!$res)
		{
		    tf_html_line("无法获取设备证书信息");
		}
        else
        {
            foreach($res as $key => $val)
            {
                
               tf_html_line($key.":".$val);
                
            }
            
            tf_html_line(NULL);
        }
		
		
		tf_put_line("</span></td>" );		
		tf_put_line( "</tr>" );		
		tf_put_line( "</table>" );
		tf_put_line( "</fieldset>" );
	}
	
	/**
	 * @name 	get_current_cert_info()
	 * @desc 	获取当前设备证书信息，若成功证书信息会已数组的形式返回，失败返回FALSE
	 */
	protected function get_current_cert_info()
	{
	
	    $infos = "";
	    $server_cert_file = "/conf/patchs/ssl_crt/server.crt";
	    $server_cert_file_default = "/copsec/soft/apache/conf/ssl.crt/server.crt";
	    $substr = "Subject Public Key Info";
	
	    $server = is_file($server_cert_file)?$server_cert_file: $server_cert_file_default;
	    $content = file_get_contents($server);
	    $info = strstr($content, $substr, true);
	
	
	    if(!$info)
	    {
	        $res = FALSE;
	    }
	    else
	    {
	
	        $infos = explode( "\n", $info );
	
	        for($i=0; $i<count($infos); $i++)
	        {
    	        $vals = explode(": ", $infos[$i]);
    	        switch (trim($vals[0])) {
    	        case 'Version':
    	            $res["证书版本"]=$vals[1];
    	            break;
                case 'Signature Algorithm':
                    $res["签名算法"]=$vals[1];
                break;
                case 'Issuer':
                    $res["发布机构"]=$vals[1];
                break;
                case 'Subject':
                     $res["发布主题"]=$vals[1];
                break;
                case 'Not Before':
                     $res["开始时间"]=$vals[1];
                break;
                case 'Not After':
                    $res["结束时间"]=$vals[1];
                break;
                default:
                   	
                break;
    	        }
	        }
	     }
	
    return $res;
}
	
	
	
	/**
	 * @name 	echo_body_contents()
	 * @desc 	输出页面body内容
	 * @param 	string $flag : 识别符
	 */
	protected function echo_body_contents( $flag = NULL )
	{
		//$this->trace_calling(__FUNCTION__ );	

		tf_html_line( NULL );
		
		switch ($flag)
		{
			case '1':
				$this->echo_current_cert_info( );
				break;
			default:
				$this->echo_cert_update_forms();
				break;
		}	
		
			
	//	$this->trace_leaving( __FUNCTION__  );
	}	
	
}

$h_logbak = new CHtml_CertUpdate_t( );

$h_logbak->display();

