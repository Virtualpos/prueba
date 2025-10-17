<?php



if (!defined("ROOT_PATH"))
{
	header("HTTP/1.1 403 Forbidden");
	exit;
}


    require_once dirname(__FILE__) . '/../plugins/pjMailchimp/MCAPI.class.php';
    require_once dirname(__FILE__) . '/../../payment/Loader/SetupClass.php';
    require_once dirname(__FILE__) . '/../../payment/Generic/PaymentFacade.php';
    require_once dirname(__FILE__) . '/../../payment/util/Services.php';


class pjFrontPromo extends pjAppController
{
	public $defaultForm = 'TSBC_Form';
	
	public $defaultCaptcha = 'TSBC_Captcha';
	
	public $defaultCart = 'TSBC_Cart';
	
	public $defaultLocale = 'TSBC_Locale';
	
	public $cart = NULL;
	
	public function __construct()
    {
        $this->setLayout('pjActionFront');
        self::allowCORS();
    }

    public function isXHR()
    {
        return parent::isXHR() || isset($_SERVER['HTTP_ORIGIN']);
    }
    
	
	
	static protected function allowCORS()
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With");
    }
    
    public function createURL() {
        
        $owners_arr = pjOwnerModel::factory()->findAll()->getData();
        
        foreach ($owners_arr as $owner) {
            
            $subdomain = pjUtil::asignFriendlyURLOwner($owner['name']);
            error_log("->>>> " . $owner['name'] . "  URL --->" . $subdomain);
            pjOwnerModel::factory()->reset()->set('id',$owner['id'] )->modify(array("subdomain"=>$subdomain));
            
        }
    }
	// Inicialioza
	public function ViewPaymentPage(){
		
		
		if (isset($_GET["uuid"])) {
			
			 $arr_pi = pjPaymentInterfaceModel::factory()
			        ->select('t1.*, t2.name as owner_name, t2.id as owner_id, t2.mail_chimp_active, t2.mailchimp_api_key, 	mail_chimp_list_id')
			        ->join('pjOwner', 't1.owner_id=t2.id', 'left')
			        ->where('t1.uuid = ', $_GET["uuid"])  
					->where('t1.is_active', 'T') 
			        ->findAll()
			        ->getData();
			
			 $arr_prod = pjPaymentInterfaceModel::factory()
			        ->select('t3.*, NOW() as date')
			        ->join('pjPaymentInterfaceProduct', 't1.id=t2.payment_interface_id', 'left')
			        ->join('pjProduct', 't2.product_id=t3.id', 'left')
			        ->where('t1.uuid = ', $_GET["uuid"])   
			        ->where('t3.available_flag = ', 'SI')
				->where('now() between t3.available_from AND DATE_ADD(t3.available_to, INTERVAL 1 DAY)')   // + 1 day para que tome hasta las 23:59:59
			        ->findAll()
			        ->getData();
			
			if (sizeof($arr_pi)==0) {
				pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=PrintError&Err=0");
			}
			
			if (sizeof($arr_prod)==0) {
				pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=PrintError&Err=1");
			}
			        
			$this->set('pi_data', $arr_pi);
			$this->set('prod_data', $arr_prod);
                        
			if (isset($_GET["layout"])) {
				$this->set('layout', $_GET["layout"]);
			}
			
			
		} else {
			pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjAdmin&action=pjActionIndex&err=AU03");
		}
        
    }
    
    
    public function ViewPaymentPageV2(){
		
	 $this->setLayout('pjActionEmpty');	
		if (isset($_GET["uuid"])) {
			
			 $arr_pi = pjPaymentInterfaceModel::factory()
			        ->select('t1.*, t2.name as owner_name, t2.telefono,t2.logo, t2.id as owner_id, t2.mail_chimp_active, t2.mailchimp_api_key, 	mail_chimp_list_id')
			        ->join('pjOwner', 't1.owner_id=t2.id', 'left')
			        ->where('t1.uuid = ', $_GET["uuid"])  
				//->where('t1.is_active', 'T') 
			        ->findAll()
			        ->getData();
			
			 $arr_prod = pjPaymentInterfaceModel::factory()
			        ->select('t3.*, NOW() as date, IF (now() between t3.available_from AND DATE_ADD(t3.available_to, INTERVAL 1 DAY), "SI", "NO") as en_fecha')
			        ->join('pjPaymentInterfaceProduct', 't1.id=t2.payment_interface_id', 'left')
			        ->join('pjProduct', 't2.product_id=t3.id', 'left')
			        ->where('t1.uuid = ', $_GET["uuid"])   
			      //  ->where('t3.available_flag = ', 'SI')
				//->where('now() between t3.available_from AND DATE_ADD(t3.available_to, INTERVAL 1 DAY)')   // + 1 day para que tome hasta las 23:59:59
			        ->orderBy("t3.id desc ")
                                 ->findAll()
			        ->getData();
			
			if (sizeof($arr_pi)==0) {
				pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=PrintError&Err=0");
			}
			
			if (sizeof($arr_prod)==0) {
				pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=PrintError&Err=1");
			}

                        
                        foreach ($arr_prod as $key => $product) {
                            if ($product['decrease_stock']=='SI') {
                                $stock = pjTransaction::availableStock($product['id']);    
                                $arr_prod[$key]['available_stock'] = $stock;
                            }else{
                                $arr_prod[$key]['available_stock'] = MAX_ITEMS_TO_SALE;
                            }
                        }
                        
                        
			$this->set('pi_data', $arr_pi);
			$this->set('prod_data', $arr_prod);
                        $this->set('uuid', $_GET["uuid"]);
                        
			
			
			
		} else {
			pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjAdmin&action=pjActionIndex&err=AU03");
		}
        
    }


    
    private function createAccount($post) {
       
      error_log("Creando cuenta");  
      $user_id = -1;

      $ruts = explode("-", str_replace(".","", $post['rut_full']));
      
      $data = array();
      $data = array_merge($_POST, $data);
      $data['rut'] = $ruts[0];
      $data['dv'] = $ruts[1]; 
      $data['autocreated'] = 'T';
      $data['max_diary_amount'] = MAX_DIARY_AMOUNT;
      $data['max_diary_transacction'] = MAX_DIARY_TRANSACTION; 
      $data['subdomain'] = pjUtil::asignFriendlyURLOwner($_POST['name']);
      
      
      error_log("Iniciando creación de Owner");
      error_log("Data: " . print_r($data, TRUE));

       // Crea Owner
      $pjOwnerModel = pjOwnerModel::factory();
      $owner_id = $pjOwnerModel->reset()->setAttributes($data)->insert()->getInsertId();

      
      error_log("Owner creado con ID: " . $owner_id);
       
      // Crea Usuario
      $pjUserModel = pjUserModel::factory();
     
      $data['owner_id']=$owner_id;
      $data['role_id']="1";
      $data['payment_notify']="T";
      $data['status']="F";
      $data['is_active']="F";
      $data['rut']=$post['rut_full'];
      $data['serial_rut']=$post['serial'];
      
      error_log("Intentando crear user con data:" . print_r($data,TRUE));
      
      $user_id = $pjUserModel->reset()->setAttributes($data)->insert()->getInsertId();
      
      
      
      error_log("Usuario creado con ID: " . $user_id);
     
      return $user_id;
        
    }
    
    
    private function createBusinessAccount($post) {
       
      error_log("Creando cuenta empresa");  
      $user_id = -1;

      $ruts = explode("-", str_replace(".","", $post['rut_full']));
      $ruts_empresa = explode("-", str_replace(".","", $post['rut_empresa']));
      
      $data = array();
      $data = array_merge($post, $data);
      $data['rut'] = $ruts_empresa[0];
      $data['dv'] = $ruts_empresa[1]; 
      $data['name'] = $post['name_empresa']; 
      $data['autocreated'] = 'T';
      $data['max_diary_amount'] = MAX_DIARY_AMOUNT;
      $data['max_diary_transacction'] = MAX_DIARY_TRANSACTION; 
      $data['subdomain'] = pjUtil::asignFriendlyURLOwner($_POST['name_empresa']);
      
      
      error_log("Iniciando creación de Business Owner " . $_POST['name_empresa']);
      error_log("Data: " . print_r($data, TRUE));

       // Crea Owner
      $pjOwnerModel = pjOwnerModel::factory();
      $owner_id = $pjOwnerModel->reset()->setAttributes($data)->insert()->getInsertId();
      
      error_log("Owner creado con ID: " . $owner_id);
       
      // Crea Usuario
      $pjUserModel = pjUserModel::factory();
      $data['name'] = $post['name']; 
      $data['owner_id']=$owner_id;
      $data['role_id']="1";
      $data['payment_notify']="T";
      $data['status']="F";
      $data['is_active']="F";
      $data['rut']=$post['rut_full'];
      $data['serial_rut']=$post['serial'];
      
      error_log("Intentando crear user con data:" . print_r($data,TRUE));
      
      $user_id = $pjUserModel->reset()->setAttributes($data)->insert()->getInsertId();
      
      error_log("Usuario creado con ID: " . $user_id);
     
      return $user_id;
        
    }
    
    function sendVerificationEmail($user_id) {
        
        
         
        $user_arr = pjUserModel::factory()->where('t1.id', $user_id)->findAll()->getData();
        $user_arr = $user_arr[0];
        
       // error_log("Enviando correo de verificación a: " . print_r($user_arr,TRUE));
        
        $token = pjTokenModel::factory()->generate($user_id);
        
         // error_log("Token: " . $token);
            
            $Email = new pjEmail();
	    $Email->setContentType('text/html');
            
            $Email->setFrom("webmaster@virtualpos.cl","Pagos Internet")
            ->setTo($user_arr['email'])
            ->setSubject('Activa tu cuenta virtualPOS', true)
            ->setTransport('aws');

            $url = PJ_WEBSITE_DOMAIN . "/admin/index.php?controller=pjFrontPromo&action=verify&token=" . $token; 

          
           /*  no funciona en este controlador   esto --------->> $this->option_arr['o_forwarding_activation_email']
            * 
            $body = str_replace(
                    array('{Name}', '{Url}'),
                    array($user_arr['name'], $url ),
                    $this->option_arr['o_forwarding_activation_email']
            );
            
            $k = new pjBatch();
              error_log("Template >>" . $k->option_arr['o_forwarding_activation_email']);
              error_log("MSG: " . $body);
            
           if ($Email->send($body))
            {
                  
                error_log("Mensaje de activación de cuenta enviado a " . $usuario['email']);
                
            } else {
                  
                error_log("No se puedo enviar mensaje de activación a " . $usuario['email']);
            } 
           */
            
        
        $Email = new pjEmail();
	$Email->setContentType('text/html');

	$Email->setFrom("webmaster@virtualpos.cl","Pagos Internet")
		  ->setTo($user_arr['email'])
                  ->setSubject('Activa tu cuenta virtualPOS', true)
                  ->setTransport('aws');
        
        $body = "<html><body>Para validar tu correo y seguir con el proceso de configuración de tu cuenta haz click en el siguiente link o copialo en tu navegador Web " . PJ_WEBSITE_DOMAIN ."/admin/index.php?controller=pjFrontPromo&action=verify&token=" . $token . "</body></html>";


      if ($Email->send($body)) {
 
            
          } else {
           error_log("Error al enviar correo de verificación a " . $user_arr['email']);  

      } 
       
        
    }
    public function test() {
         $body = str_replace(
                    array('{Name}', '{Url}'),
                    array("Cristian", "https://www.virtualpos.cl" ),
                    $this->option_arr['o_forwarding_activation_email']
            );
         error_log("Template >>" . $k->option_arr['o_forwarding_activation_email']);
         error_log("MSG: " . $body);
         
    }


    public function signinFinish() {
        
        // // error_log("--->> ejecutando signinFinish <<------");
         
        // // error_log("POST" . print_r($_POST, TRUE));
        $this->setLayout('pjActionSignin');
        
        if (isset($_POST['bank_account_code'])
                &&isset($_POST['bank_account_type'])
                &&isset($_POST['bank_account_number'])
                &&isset($_POST['bank_account_name'])
                &&isset($_POST['bank_account_rut'])
                &&isset($_POST['token'])       
                &&isset($_POST['bank_account_email'])) {


                   // // error_log("Update con token:" . $_POST['token']);
                    
                    $token_arr = pjTokenModel::factory()
                            ->where('token',$_POST['token'])
                            ->where('verified','F')
                            ->findAll()
                            ->getData();

                    $user_arr = pjUserModel::factory()->find($token_arr[0]['user_id'])->getData();
                    $owner_arr = pjOwnerModel::factory()->find($user_arr['owner_id'])->getData();
                    
                    // // error_log("Data Owner antes:" . print_r($owner_arr, TRUE));

                    if (sizeof($owner_arr)) {

                    //   // error_log("Actualizando datos de Owner con cta bancaria");

                       // Update data owner 
                       $data = array();
                       $data = array_merge($_POST, $data);
                       $data['is_active'] = 'T';
                       $data['bank_account_rut'] = str_replace('.','',$data['bank_account_rut']);
                       
                       # API Keys
                       $data['secret_key'] = md5(uniqid(rand(), true));
                       $data['api_key'] = implode('-', str_split(substr(strtolower(md5(microtime().rand(1000, 9999))), 0, 30), 6));



                        // Update owner
                       pjOwnerModel::factory()->reset()->set('id',$owner_arr['id'] )->modify($data);

                       $owner_arr = pjOwnerModel::factory()->find($user_arr['owner_id'])->getData();
                    
                     //   // error_log("Data Owner después:" . print_r($owner_arr, TRUE));
                        
                      // activate user
                       $data_user = array();
                       $data_user['status']="T";
                       $data_user['is_active']="T";
                       
                       pjUserModel::factory()->reset()->set('id',$user_arr['id'] )->modify($data_user);
                      // // error_log("Updating token id : " . $token_arr[0]['id']);
                       pjTokenModel::factory()->reset()->set('id',$token_arr[0]['id'] )->modify(array('verified'=>'T'));
                       
                       $this->contractPlanSignin($owner_arr['id']);
                        
                       $this->set('user_arr', $user_arr);

                }
                else {

                }
            }
        
    }
    
    
 
    
    
    public function pjActionCaptcha()
    {
        $this->setAjax(true);
       // $arr = pjFormModel::factory()->find($_GET['id'])->getData();
       // if($arr['captcha_type'] == 'string'){
            $Captcha = new pjCaptcha('app/plugins/pjInstaller/web/fonts/Anorexia.ttf', $this->defaultCaptcha . $_GET['id'], 6);
            $Captcha->setImage('app/plugins/pjInstaller/web/img/button.png')->init(isset($_GET['rand']) ? $_GET['rand'] : null);
       /* }else{
            $Captcha = new Captcha('app/web/obj/verdana.ttf', $this->defaultCaptcha . $_GET['id'], 6);
            $Captcha->setWidth(120);
            $Captcha->setImage('app/web/img/button-captcha.png');
            $Captcha->init(isset($_GET['rand']) ? $_GET['rand'] : null);
        } */
    }
    
    
    public function pjActionCheckCaptcha()
    {
        $this->setAjax(true);
        if (!isset($_GET['captcha']) || empty($_GET['captcha']) || strtoupper($_GET['captcha']) != $_SESSION[$this->defaultCaptcha . $_GET['id']]){
        	
        	if (!isset($_GET['captcha'])) { 
        	  // error_log("Check CAPTCHA : ERR_1 Valor codigo no enviado en GET");
        	} else if (empty($_GET['captcha'])) {
        	  // error_log("Check CAPTCHA : ERR_2 Valor codigo vacio");
        	} else if (empty($_SESSION[$this->defaultCaptcha . $_GET['id']])) {
        	  // error_log("Check CAPTCHA : ERR_3 Perdida de valor de captcha en sesion");
        	
        	} else if (strtoupper($_GET['captcha']) != $_SESSION[$this->defaultCaptcha . $_GET['id']]) {
        	  // error_log("Check CAPTCHA : ERR_4 Codigo no coincide ingresado = ".$_GET['captcha']." en sesion = ".$_SESSION[$this->defaultCaptcha . $_GET['id']] );
        	};
			echo 'false';
        }else{
            echo 'true';
        }
    }
    
    
    public function validateSerialRut() {
         
         $this->setAjax(true);
         

          if (isset($_GET['rut'])&&isset($_GET['serial'])) {
            
            $rut = $_GET['rut']; 
            $rut = str_replace(".","",$rut);
            $rut = strtoupper($rut);
            
            $serial = $_GET['serial'];
            $serial = str_replace(".","",$serial);
            $serial = strtoupper($serial);
            
            $arrContextOptions=array(
                "ssl"=>array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ),
            );
             
            $page = file_get_contents('https://portal.sidiv.registrocivil.cl/usuarios-portal/pages/DocumentRequestStatus.xhtml?RUN='.$rut.'&type=CEDULA&serial=' . $serial, true, stream_context_create($arrContextOptions));
            $pos = strpos($page, 'Vigente');

            if ($pos === false) {
                 echo 'false';
            } else {
                echo 'true';  
            } 
             
         } else {
             echo 'false';
         }
                 
    }
    
    
    
    
    public function verify(){
        
        $this->setLayout('pjActionSignin');
        
        if (isset($_GET['token'])) {
            // error_log("Verificando token:" . $_GET['token']);
           
            $token_arr = pjTokenModel::factory()
                            ->where('token',$_GET['token'])
                            ->where('verified','F')
                            ->findAll()
                            ->getData();
            
            
            if (sizeof($token_arr)<1) {
                //token no existe, redirige a login
                
                // error_log("Redirigiendo a login");
                pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjAdmin&action=pjActionLogin&err=token_consumed");
                
                
            } else {
                
                    // token OK, se recupera info de usuario, se marca como consumido el token y se deriva a siguiente página del proceso
                    
                    // error_log("token OK:"  . print_r($token_arr,TRUE));
                    
                    $user_arr = pjUserModel::factory()->find($token_arr[0]['user_id'])->getData();
                    
                    // error_log("Usuario:"  . print_r($user_arr,TRUE));
                    
                    $owner_arr = pjOwnerModel::factory()->find($user_arr['owner_id'])->getData();
                    
                    // error_log("Owner:"  . print_r($owner_arr,TRUE));
                    
                    $bank_arr = pjBankModel::factory()->orderBy("nombre")->findAll()->getData();
                    
                    // error_log("Banks:"  . print_r($bancos_arr,TRUE));
                    
                    $this->set('user_arr', $user_arr);
                    $this->set('owner_arr', $owner_arr);
                    $this->set('bank_arr', $bank_arr);
   
                
            }
        } else {
            // Error no viene el token
             //token no existe, redirige a login
                pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjAdmin&action=pjActionLogin&err=token_null");
        }
    }

    public function contractPlanSignin($owner_id) {
        
            $contract_arr = array();
            $contract_arr['owner_id'] = $owner_id;
            $contract_arr['plan_id'] = PLAN_SIGNIN_PROMO;
            $contract_arr['start_date'] = ':NOW()';
            $contract_arr['end_date'] = ':DATE_ADD(NOW(), INTERVAL 2 YEAR)';
                    
       $contract_id = pjContractPlanModel::factory()->reset()->setAttributes($contract_arr)->insert()->getInsertId();
        
        $pm_plan_arr = pjPlanPaymentMethodModel::factory()->where("plan_id", PLAN_SIGNIN_PROMO)->findAll()->getData();
        
         // error_log("$pm_plan_arr : " . print_r($pm_plan_arr, TRUE));
        
        $pm_attr_arr = array();
        
        foreach ($pm_plan_arr as $pm) {
            
            $attr_arr = pjAttributePaymentMethodModel::factory()->where("payment_method_id", $pm['payment_method_id'])->findAll()->getData();
            
            array_push($pm_attr_arr, $attr_arr);
            
        }
        
        // error_log("Array de atributos : " . print_r($pm_attr_arr, TRUE));
        
        $pjPaymentMethodAttribute = pjPaymentMethodAttributeConfigModel::factory();
        
        
        
        foreach ($pm_attr_arr as $attribute) {
            
            foreach ($attribute as $value) {
                
           
           
                // error_log("-------------> attribute: " . print_r($value, TRUE));

                $reg_conf = array();
                $reg_conf['contract_plan_id'] = $contract_id;
                $reg_conf['payment_method_attribute_id'] = $value['id'];
                $reg_conf['payment_method_attribute_value'] = $value['attribute_default_value'];
                $reg_conf['date'] = ':NOW()';

                // error_log("Insertando array: " . print_r($reg_conf, TRUE));

                $id_conf = $pjPaymentMethodAttribute->reset()->setAttributes($reg_conf)->insert()->getInsertId();

                 // error_log("Position Id: " . $id_conf);
            }
        }         
        
    }
    
    public function printPlan() {
        $this->contractPlanSignin(55);
    }
    

    public function signin() {
        
         $this->setLayout('pjActionSignin');
         $this->set("captchaId", 12345);
        
       if (isset($_POST['signin'])) {
            if (isset($_POST['name'])&&isset($_POST['email'])&&isset($_POST['phone'])&&isset($_POST['password'])&&isset($_POST['tipo'])&&isset($_POST['rut_full'])&&isset($_POST['captcha'])) {
                 error_log("POST recibido: " . print_r($_POST, TRUE));
                
                
                $pjUserModel = pjUserModel::factory();
                
                 if (0 != $pjUserModel->where('t1.email', $_POST['email'])->findCount()->getData())
                 {
                     //existe usuario registrado con el mismo correo
                     error_log("Usuario ya registrado con correo " . $_POST['email']);
                     pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFrontPromo&action=signinKO&err=01"); 
                 }
                
                 if ($_POST['tipo']=='EMPRESA') {
                     $new_user_id = $this->createBusinessAccount($_POST);
                 }  else {
                     $new_user_id = $this->createAccount($_POST);
                 }
                 
                 
                 if ($new_user_id>0) {
                     $this->sendVerificationEmail($new_user_id);
                 } 
                  error_log("Correo enviado OK");
                // $this->set('email', $this->maskEmail($_POST['email']));
                 
                 pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFrontPromo&action=signinOK&email=" . $this->maskEmail($_POST['email']));

            } else {
                  error_log("Error, faltan datos por ingresar");
                 pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFrontPromo&action=signinKO&err=02"); 
            }
       } else {
          // do nothing --> show UI
                
                
          	$this->appendJs('jquery.validate.min.js', PJ_THIRD_PARTY_PATH . 'validate/');
         
       }
        
              
    }
    
    public function signinFromVirtualShop() {
        
         $this->setLayout('pjActionEmpty');
         $this->set("captchaId", 12345);
        
       if (isset($_POST['signin'])) {
            if (isset($_POST['name'])&&isset($_POST['email'])&&isset($_POST['phone'])&&isset($_POST['password'])&&isset($_POST['rut_full'])&&isset($_POST['captcha'])) {
                 error_log("POST recibido: " . print_r($_POST, TRUE));
                 error_log("Todos los parámetros de registro estan ingresados OK");
                
                $pjUserModel = pjUserModel::factory();
                
                 if (0 != $pjUserModel->where('t1.email', $_POST['email'])->findCount()->getData())
                 {
                     //existe usuario registrado con el mismo correo
                     error_log("Usuario ya registrado con correo " . $_POST['email']);
                     pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=signinKO&err=01"); 
                 }
                
                 $new_user_id = $this->createAccount($_POST);
                 
                 if ($new_user_id>0) {
                     $this->sendVerificationEmail($new_user_id);
                 } 
                  error_log("Correo enviado OK");
                // $this->set('email', $this->maskEmail($_POST['email']));
                 
                 pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=signinOK&email=" . $this->maskEmail($_POST['email']));

            } else {
                  error_log("Error, faltan datos por ingresar");
                 pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=signinKO&err=02"); 
            }
       } else {
          // do nothing --> show UI
                
                
          	$this->appendJs('jquery.validate.min.js', PJ_THIRD_PARTY_PATH . 'validate/');
         
       }
        
              
    }
    

    
    public function signinOK() {
       $this->setLayout('pjActionSignin');
        
    }
    
    public function signinKO() {
        $this->setLayout('pjActionSignin');
        
    }
    
    function maskEmail($email)
    {
        list($username, $domain) = explode('@', $email);
        
        
        $len = strlen($username);
        if ($len>4) {
            $start= 3;
            $end =$len - ($len-2);
        } else {
            $start= 1;
            $end =$len - ($len-1);
        }
        
        return substr($email, 0, $start) . str_repeat('*', $len - ($start + $end)) . substr($email, $len - $end, $end) . "@" . $domain;
    }
    
    function testMask() {
        $email = "cristianporflitt@gmail.com";
        echo $this->maskEmail($email);
        
    }


    
 
 
 

    public function PrintError(){
		
		switch ($_GET["Err"]) {
		    case 0:
		        $this->set('errorMsg', "Página de pago no disponible");
		        break;
		    case 1:
		         $this->set('errorMsg', "No existen productos o servicios disponibles para pagar desde esta página");
		        break;
		    default:
				 $this->set('errorMsg', "Ha ocurrido un error, no es posible atenderlo en este momento");
		}
		
    }
/*    public function afterFilter(){	
    }
	
    public function beforeFilter(){
    }
*/
    public function beforeRender(){
    }
	
	protected function getCart($cid)
	{
		return $this->cart->getAll();
	}
	private function pjActionSetLocale($locale)
	{
		if ((int) $locale > 0)
		{
			$_SESSION[$this->defaultLocale] = (int) $locale;
		}
		return $this;
	}
	
	public function pjActionGetLocale()
	{
		return isset($_SESSION[$this->defaultLocale]) && (int) $_SESSION[$this->defaultLocale] > 0 ? (int) $_SESSION[$this->defaultLocale] : FALSE;
	}
        
        
        public function pjActionDoPay(){ 
             $this->setLayout('pjActionPayment'); 
             $this->set('forward', $this->pjActionProcessDoPay($_GET["uuid"]));
        }
        
        public function pjActionRetryPayment(){ 
             $this->setLayout('pjActionPayment'); 
             $this->set('forward', $this->pjActionProcessDoPay($_POST["uuid"]));
        }
        
        
        
        public function pjActionProcessDoPay($uuid){ 
        
           
            // FILTRA Y VALIDA SI VIENE EL UUID
            if(!isset($uuid) || $uuid==''){
               return "NO SE PUEDE PROCESAR LA TRANSACCION, EL PARAMETRO UUID ES INCORRECTO";
                
            }else{
                $order = array();
                $order['uuid'] = $uuid;
                $httpPosString ='';

                // OBTIENE LA INFORMACION DE LA ORDER CON EL UUID
                $order_array = pjOrderModel::factory()
                ->select('t1.*')
                ->where('t1.uuid = ', $uuid)   
                ->findAll()
                ->getData();
                
                 $owner = pjOwnerModel::factory()
                ->select("t1.*")
                ->where('t1.id', $order_array[0]['owner_id'])
                ->findAll()->getData();

                if(count($order_array) !=1 || strcmp($order_array[0]['status'],"pendiente")!=0){
                    //LOGEA EL ERROR
                    return "No es posible procesar el pago. La orden se encuentra en estado: ". $order_array[0]['status'];
                    

                }else{
                    if(pjTransaction::isTrxCollectorType($order_array[0]['id']) && !pjTransaction::isAuthorizable($order_array[0]) ){
                        
                        // complemento de telefono
                        $msg_phone = "";
                        if ($owner[0]['telefono']!='') $msg_phone = " al teléfono " . $owner[0]['telefono'];
                        
                        return "No es posible procesar este pago, por favor comunícate con ". $owner[0]['name'] .   $msg_phone;
                        
                    }else{
                        //RESTRICCIONES OK, SE CONTINUA CON LA EJECUCION...
                        //OBTIENE EL MEDIO DE PAGO SELECCIONADO POR EL CLIENTE
                        $payment_method_array = pjPaymentMethodModel::factory()
                        ->select('t1.*')
                        ->where('t1.id = ', $order_array[0]['payment_method_id'])   
                        ->findAll()
                        ->getData();

                        //HUBO UN ERROR AL OBTENER EL MEDIO DE PAGO, SE REGISTRA EL PROBLEMA Y SE EXCEPCIONA
                        if(count($payment_method_array) !=1){
                            return "Ocurrió un error al procesar el pago, por favor intentalo nuevamente.";
                            

                        }else{
                            $payment_method_attribute_config_model_array = pjAppModel::factory()
                            ->prepare(sprintf("SELECT a.*, d.attribute 
                                FROM  ts_booking_payment_method_attribute_config a, ts_booking_contracts_plans c, ts_booking_attributes_payment_methods d
                                where a.contract_plan_id = c.id
                                and c.end_date > now()
                                and d.payment_method_id = %1\$d
                                and c.owner_id = %2\$d
                                and a.payment_method_attribute_id = d.id",$order_array[0]['payment_method_id'],$order_array[0]['owner_id']

                            ))
                            ->exec()
                            ->getData();
                                
                            error_log(print_r($payment_method_attribute_config_model_array,true));
                            
                            
                            $commerce_code = '';
                            foreach ($payment_method_attribute_config_model_array as $key => $value) {
                                if($value['attribute'] == 'commerce_code'){
                                   $commerce_code =  $value['payment_method_attribute_value'];
                                    break;
                                }
                            }

                            $paymentMethodId = $order_array[0]['payment_method_id'];
                            
                              # Registra IP creación de order
                             // error_log("Recuperando IP de pagador...");
                                $infoGeoIp = pjTransaction::getInfoIP();

                                if (sizeof($infoGeoIp)>0) {
                                    $dataOrder = array();
                                    $dataOrder['ip_city_payment'] = $infoGeoIp['city'];
                                    $dataOrder['ip_country_payment'] = $infoGeoIp['country'];
                                    $dataOrder['ip_lat_payment'] = $infoGeoIp['lat'];
                                    $dataOrder['ip_lon_payment'] = $infoGeoIp['lon'];
                                    $dataOrder['ip_geo_payment'] = $infoGeoIp['query'];  
                                    
                                   // error_log(" IP de pagador ORDER ". $order_array[0]['id'] ." = " . print_r($dataOrder, true));
                                    
                                    pjOrderModel::factory()->reset()->set('id',$order_array[0]['id'] )->modify($dataOrder);

                                }

                            switch ($paymentMethodId) {
                                // Webpay
                                case 1:
                                        pjTransaction::logger($order_array[0]['id'], LOGGER_INFO, "Iniciando el pago con tarjetas bancarias " , 'T');
                                        $httpPosString = $this->webpay($uuid, $order_array[0], $commerce_code);
                                        break;
                                // PatPass by Webpay    
                                case 2:

                                    break;
                                // Khipu    
                                case 3:

                                    break;
                                // Servipag    
                                 case 4:

                                    break;       
                                default:

                                    break;
                            }
                           // $this->set('forward', $httpPosString);
                            return $httpPosString;
                        } 
                    }
                }
            }
    }
    public function webpay($uuid, $order, $commerce_code){
         
        $setup = new SetupClass();
        $setup->loadContent();
            
        $total = $order['deposit'];
        $service_charge =  '0';
        $webpay_commerce_code = $commerce_code;
            
        $wp_t1_buyOrder = "OCx".str_pad($order['owner_id'],6,"0",STR_PAD_LEFT)."x".$uuid;
        $wp_sessionId = "SIDx".$uuid."x".date("Y-m-d-H-i-s");    
        
        $paymentFacade = PaymentFacade::create($setup->commerceConfigValue(), $setup->servicesParams())->withUuidAndAmount($uuid, $total, $service_charge, $webpay_commerce_code);
        $transaction = $paymentFacade->initTransaction($wp_t1_buyOrder, $wp_sessionId);
        
        $httpPos = $this->printHttpPos($transaction->transactionUrl, $transaction->transactionToken, $wp_t1_buyOrder);
        
        pjTransaction::logger($order['id'], LOGGER_INFO, "Token de transación Webpay = " . $transaction->transactionToken , 'F');
       
        
        return $httpPos;
    
    }
    
    public function printHttpPos($transactionUrl, $transactionToken, $buyOrder){
        
        $httpPos = "<form id='tokenForm' method='post' action='".$transactionUrl."'>
                        <input type='hidden' name='token_ws' value='".$transactionToken."' />
                        <input type='hidden' name='buy_order' value='".$buyOrder."' />
                    </form>
                    <script type='text/javascript'>
                        document.getElementById('tokenForm').submit();
                    </script>";    
        error_log($httpPos);
        return $httpPos;
    }
    
    // Procesa POS de Pagina de Pago
	public function PaymentDispatcher(){
		
		$this->setLayout('pjActionEmpty');
		
		
		if (isset($_POST['payment_interface_uuid'])) {
			
			error_log("pjFront.PaymentDispatcher > payment_interface_id : " . $_POST['payment_interface_uuid']);
			
			$arr_pi = pjPaymentInterfaceModel::factory()
                                    ->select("t1.*")
                                    ->where("t1.uuid", $_POST['payment_interface_uuid'])
                                    ->where("t1.is_active", 'T')
                                    ->findAll()
                                    ->getData();
						
			if (sizeof($arr_pi)==0) {
				error_log("pjFront.PaymentDispatcher > arr_pi == 0 para payment_interface_uuid : " . $_POST['payment_interface_uuid']);
				pjUtil::redirect($_SERVER['PHP_SELF'] . "?controller=pjFront&action=PrintError&Err=0");
			}

		}
		
		$arr_owner = pjOwnerModel::factory()
                                ->select("t1.*")
                                ->where("t1.ID", $arr_pi[0]['owner_id'])
                                ->findAll()
                                ->getData();
		 
		
		
		$owner_id=$_POST['owner_id'];
		$type=$_POST['p_type'];
                $social_id=urlencode($_POST['social_id']);
		$first_name=urlencode($_POST['first_name']);
		$last_name=urlencode($_POST['last_name']);
		$email=urlencode($_POST['email']);
		$notes=urlencode($_POST['p_notes']);
		$product_id=$_POST['product_id'];
		$payment_method_id=$_POST['payment_method_id'];
		$q=$_POST['q'];

		
		// **********  Datos cliente  //////////////////////////////////////
                $dataClient = array();
                $dataClient['owner_id'] = $arr_pi[0]["owner_id"];
                $dataClient['social_id'] = $_POST["social_id"];
                $dataClient['first_name'] = $_POST["first_name"];
                $dataClient['last_name'] = $_POST["last_name"];
                $dataClient['email'] = $_POST["email"];
		
        
		// ¿ Existe cliente email en el owner ?
                $client = pjClientModel::factory()
                            ->select('t1.id')
                            ->where('t1.email = ', $_POST["email"]) 
                            ->where('t1.owner_id = ', $arr_pi[0]["owner_id"])   
                            ->findAll()
                            ->getData();
        
                $ClientId=-1;
        
                if (count($client) == 0) {
                        // Crea cliente y asociarlo a Owner 
                        $pjClientModel = pjClientModel::factory();  
                        $ClientId = $pjClientModel->reset()->setAttributes($dataClient)->insert()->getInsertId();
                        error_log("pjFront.PaymentDispatcher > Nuevo cliente creado Id : " . $ClientId);
                } 
                 else 
                {
                  $ClientId = $client[0]['id'];
                  error_log("pjFront.PaymentDispatcher > Detectado cliente Id : " . $ClientId);
                }
                
                // PLAN INFO
                
                
               $contracts_owner_arr = pjContractPlanModel::factory()
                                ->select('t1.*')
                                ->join('pjPlan', 't1.plan_id=t2.id', 'inner')
                                ->where('t1.owner_id', $arr_pi[0]["owner_id"])
                                ->limit(1)
				->findAll()
				->getData();
                
               $contracts_owner_arr = $contracts_owner_arr[0];
                
               error_log("pjFront >> Plan contratado:" . print_r($contracts_owner_arr, TRUE));
        


                // Precio producto
                $arr = pjProductModel::factory()
                    ->select('short_description, base_price as price, tax, base_price*tax/100 as total_tax, base_price*(100+tax)/100 as total')
                    ->where('t1.id = ', $_POST["product_id"])   
                    ->findAll()
                    ->getData();
        
        
                // ***** Datos Order  ////////////////////////////////////////////////////
        
                // UUID Order
               $uuid = str_pad($arr_pi[0]["owner_id"],3,"0",STR_PAD_LEFT).uniqid(); 

               $dataOrder = array();

               $dataOrder['uuid'] = $uuid;
               $dataOrder['owner_id'] = $arr_pi[0]["owner_id"];
               $dataOrder['client_id'] = $ClientId;

                   // Esto hay que adaptarlo para que tome todos los productos de un carro
               $dataOrder['price'] = $arr[0]['price'] * $_POST["q"];
               $dataOrder['tax'] = round($arr[0]['total_tax'] * $_POST["q"]);
               $dataOrder['total']   = round($arr[0]['total'] * $_POST["q"]);
               $dataOrder['deposit'] = round($arr[0]['total'] * $_POST["q"]);

               $dataOrder['status'] = "pendiente";
               $dataOrder['payment_method_id'] = $_POST["payment_method_id"];
               $dataOrder['payment_interface_id'] = $arr_pi[0]["id"];

               $dataOrder['notes'] = $_POST["p_notes"];
               $dataOrder['contract_plan_id'] = $contracts_owner_arr['id'];
            
           //  $dataOrder['ip'] = $_GET["ip"];
    
	       // Registra Order
               $pjOrderModel = pjOrderModel::factory();    
               $OrderId = $pjOrderModel->reset()->setAttributes($dataOrder)->insert()->getInsertId();
		
               error_log("pjFront.PaymentDispatcher > Orden generada : " . $OrderId);
		
		// Items data
	       $dataOrderItems  = array('order_id' => $OrderId, 
                                         'product_id'=> $_POST["product_id"], 
                                         'price'=> $arr[0]['price'], 
                                         'tax'=>$arr[0]['total_tax'], 
                                         'total'=>$arr[0]['total'],
                                         'units'=>$_POST["q"]);
		
		$pjOrderItemModel = pjOrderItemModel::factory();    
                $ItemId = $pjOrderItemModel->reset()->setAttributes($dataOrderItems)->insert()->getInsertId();
        
                pjTransaction::logger($OrderId, LOGGER_INFO, "Orden de recaudación generada desde formulario de pago." , 'T');
        
                //crea 2 variables en las cookies para recuperar ordenes que aun se encuentran pendientes de pago                      
                setcookie('VPOS_PENDING', $_POST['payment_interface_uuid'], time()+COOKIE_TIME_SESSION, COOKIE_CONTEXT_DOMAIN, COOKIE_DOMAIN);
                setcookie('UUID_PENDING', $uuid, time()+COOKIE_TIME_SESSION, COOKIE_CONTEXT_DOMAIN, COOKIE_DOMAIN);


		// Suscripción mailChimp
		 if ((isset($_POST['suscribe']) ) && ($_POST['suscribe']=='S')) {
		
                        try {
                                error_log("pjFront.PaymentDispatcher > Intentando suscripcion MailChimp");

                                $apikey=$arr_owner[0]['mailchimp_api_key'];
                                $listId=$arr_owner[0]['mail_chimp_list_id'];

                                $mailchimp = new MCAPI($apikey,true);

                                $merge_vars = array('FNAME'=>$first_name.' '.$last_name);

                                $resultado = $mailchimp->listSubscribe( $listId, $_POST['email'], $merge_vars );

                                //Controlamos y registramos el resultado
                                if ($mailchimp->errorCode)
                                {
                                    error_log("pjFront.PaymentDispatcher > Error MC Suscription info: Owner = " .$arr_owner[0]['id'] . " E-Mail = ".$email . " Code = " . $mailchimp->errorCode . " Msg = " . $mailchimp->errorMessage);
                                } 
                                else 
                                {
                                   error_log("pjFront.PaymentDispatcher > OK MC Suscription info: Owner = " . $arr_owner[0]['id'] . "E-Mail = " . $email);
                                }
                        } catch (Exception $e) {
                                error_log("pjFront.PaymentDispatcher > ERR General Suscripcion MailChimp: " . $e->getMessage() . "\n" );
                        }
                        
		  }
		 
		  $order_array = pjOrderModel::factory()
                                    ->select('t1.*')
                                    ->where('t1.id = ', $OrderId)   
                                    ->findAll()
                                    ->getData();
		 
		 error_log("pjFront.PaymentDispatcher > Iniciando PayMentForward para Order UUID :" .  $order_array[0]['uuid']);
		// $html = $this->getOrderPaymentDataForward($order_array[0]['uuid']);
		$html = $this->pjActionProcessDoPay($order_array[0]['uuid']);
		 error_log("pjFront.PaymentDispatcher > Finalizado PayMentForward para Order UUID :" .  $order_array[0]['uuid']);
		 
                $this->set('html', $html);
		
	}
     public function viewProduct(){
         
        if (isset($_GET['friendly_name']) && isset($_GET['uuid'])) { 
            $this->setLayout('pjActionViewProduct');

            
            //OBTIENE EL OWNER A PARTIR DEL NOMBRE QUE LLEGA POR PARAMETRO EN LA URL Y ADEMAS VALIDA QUE ESTE ACTIVO 
            $owners = pjTransaction::getOwner($_GET["friendly_name"]);
            if(sizeof($owners)==0){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=1&error=0");
            }
            $owner = $owners[0];
            $owner_id =  $owner["id"];
            
            //OBTIENE EL PRODUCTO A PARTIR DEL NOMBRE QUE LLEGA POR PARAMETRO EN LA URL Y ADEMAS VALIDA QUE ESTE ACTIVO 
            $products = pjTransaction::getProduct($_GET["uuid"]);
            if(sizeof($products)==0){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=1&error=1");
            }
            $product = $products[0];
            $product_id = $product["id"];
            
           
            // OBTENER EL PRODUCTO
            $products = pjProductModel::factory()
                ->select('t1.*, ROUND(t1.base_price*(100+t1.tax)/100) as total')
                ->where('t1.id = ', $product_id)
                ->where('t1.owner_id = ',$owner_id)    
                ->findAll()
                ->getData();
            
            //VALIDA DE QUE EXISTA EL PRODUCTO
            if (sizeof($products)==0) {
                    pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=1&error=2");
            }
            
            $available_stock = 0;
            
            
            //VALIDA QUE EXISTA STOCK CONSIDERANDO EL FLAG REBAJAR STOCK(PRODUCTO)
            if($products[0]['type']=="PRODUCTO" && $products[0]['decrease_stock']=="SI"){
                $available_stock = pjTransaction::availableStock($product_id);
                $this->set("available_stock", $available_stock>=MAX_ITEMS_TO_SALE?MAX_ITEMS_TO_SALE:$available_stock);
            }else if ($products[0]['type']=='PRODUCTO' && $products[0]['decrease_stock']=="NO"){
                $this->set("available_stock", MAX_ITEMS_TO_SALE);
            }
            
            //VALIDA CAPACITY_SALES, VENTA DE SERVICIOS
            
             if($products[0]['type']=="SERVICIO" && $products[0]['capacity_sales_period']=="TOTAL_SUSCRIPCIONES"){
                
                $available_stock = pjTransaction::availableStock($product_id);
                 error_log("available_stock".$available_stock);
                $this->set("available_stock", $available_stock>=MAX_ITEMS_TO_SALE?MAX_ITEMS_TO_SALE:$available_stock);
                 
             }else if ($products[0]['type']=="SERVICIO" && $products[0]['capacity_sales_period']=="VENTAS_MENSUALES"){
                $available_stock = pjTransaction::availableCapacitySalesMonthly($product_id);
                $this->set("available_stock", $available_stock>=MAX_ITEMS_TO_SALE?MAX_ITEMS_TO_SALE:$available_stock);
             } else if ($products[0]['type']=="SERVICIO" && $products[0]['capacity_sales_period']=="VENTAS_DIARIAS"){
                $available_stock = pjTransaction::availableCapacitySalesDaily($product_id);
                $this->set("available_stock", $available_stock>=MAX_ITEMS_TO_SALE?MAX_ITEMS_TO_SALE:$available_stock);
             }
            
            
            
            $this->set('product', $products[0]);
            $this->set('owner', $owners[0]);

            $images = pjImagenProductoModel::factory()
                ->select("t1.path")
                ->join('pjProduct', 't1.object_id=t2.id', 'inner')    
                ->join('pjOwner', 't2.owner_id=t3.id', 'inner')    
                ->where('t1.object_id',$product_id)
                ->findAll()
                ->getData();
            
            $this->set('images', $images);
        }
        else{
            pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=1&error=3");
        }
    }
        
    
    public function pagame() {

        $this->setLayout('pjActionPagame');
        
        if (isset($_POST['id']) && $_POST['id'] != null) {
            $owner_id = $_POST['id'];
            $monto = $_POST['monto'];
            $name = $_POST['name'];
            $max = $_POST['max'];
            $subdomain = $_POST['subdomain'];
            $_SESSION['id'] = $owner_id;
            $_SESSION['monto'] = $monto;
            $_SESSION['name'] = $name;
            
            if ($monto > $max) {
                pjUtil::redirect("index.php?controller=pjFront&action=pagame&friendly_name=".$subdomain."&monto=".$monto."&err=01");
            }
            pjUtil::redirect("index.php?controller=pjFront&action=pagameDatos");
            
        } else {
            $owners = pjTransaction::getOwner($_GET["friendly_name"]);
            if(sizeof($owners)==0){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=1&error=0");
            }
            $owner = $owners[0];
            
            $montos= $_GET["monto"];
            
            if ($montos < '0' || $montos < MIN_AMOUNT_TO_PAY || $montos == '') {
                $montos='';
                $mensaje = "El monto mínimo de pago es de $".MIN_AMOUNT_TO_PAY;
            }
            $max = $owners[0]['max_diary_amount'];
            if ($max == 0) {
                $max = MAX_AMOUNT_TO_PAY;
            }
            $mensaje2 = "El monto máximo de transacción es de $".$max;
            $err = $_GET['err'];
            
            if ($err=='') {
                
            } else {
                $mensaje = "Error al realizar pago, el monto máximo es de $".$max;
                $this->set('mensaje3', $mensaje);
            }
            
            $this->set('monto', $montos);
            $this->set('max', $max);
            $this->set('arr', $owners);
        }
    }
    
    public function pagameDatos() {
        $this->setLayout('pjActionPagame');
        if (isset($_POST['id']) && $_POST['id'] != null) {
            $notes = "";
            $email = $_POST['email'];
            $social_id = $_POST['social_id'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $name = $_POST['name'];
            $monto = $_POST['montoa'];
            $payment_interface = 99999999;
            $tax = 0;
            $owner_id = $_POST['id'];
            
            $data = array();
            $data['owner_id'] = $owner_id;   

            $data['type'] = "GENERICO";
            $data['created_user_id'] = $owner_id;
            $data['updated_user_id'] = $owner_id;
            $data['available_from'] = ':NOW()';
            $data['available_to'] = ':NOW() + INTERVAL 30 DAY';
            $data['available_flag'] = 'SI';
            $data['base_price'] = $monto;
            $data['currency'] = "CLP";
            $data['tax'] = $tax;
            $data['name'] = $name;
            $data['short_description'] = "";
            $data['decrease_stock'] = "NO";
            $data['stock'] = "0";
            $data['sell_without_stock'] = "SI";
            
            $total_tax = round(($monto * $tax)/100);
            $price = round($monto*(100+$tax)/100); 
            $deposit = $price;
            
            
            
            $client_id = $this->getClientId($owner_id, $email, $first_name, $last_name, $social_id);
            if (sizeof($client_id) == 0) {
                pjUtil::redirect("index.php?controller=pjFront&action=pagame&err=AU01");
            }
            $pjProductModel = pjProductModel::factory();
		  
            $product_id = $pjProductModel->reset()->setAttributes($data)->insert()->getInsertId();
            
            $order_id = $this->generarOrden($owner_id, $client_id, $monto, $total_tax, $payment_interface, $notes, $product_id, $price, $deposit);
             pjTransaction::logger($order_id, LOGGER_INFO, "Solicitud de pago creada desde link de pago." , 'T');
            
            $order_uuid = pjOrderModel::factory()->where('id',$order_id)->findAll()->getData();
            
            if (sizeof($order_uuid) == 0) {
                pjUtil::redirect("index.php?controller=pjFront&action=pagame&err=AU01");
            }
            
            $this->setLayout('pjActionPayment'); 
            $this->set('forward', $this->pjActionProcessDoPay($order_uuid[0]["uuid"]));
             
        }
        
        $arr = pjOwnerModel::factory()->where('id',$_SESSION['id'])->findAll()->getData();
        $this->set('arr', $arr);
    }
    
    public function pjActionPayMe(){ 
        $this->setLayout('pjActionPayment'); 
        $this->set('forward', $this->pjActionProcessDoPay($_GET["uuid"]));
    }


    public function getClientId($owner, $email, $first_name, $last_name, $social_id) {
        $client = pjClientModel::factory()->select('t1.id')->where('t1.email = ', $email)->where('t1.owner_id = ',$owner)->findAll()->getData();
        
            $ClientId=-1;
        
            // pregunta si existe el cliente en el listado retornado por la query
            if (count($client) == 0) {
                // cliente no existe en la BD, por lo que lo crea y lo asocia al owner 

                $dataClient = array();
                $dataClient['owner_id'] = $owner;
                $dataClient['social_id'] = $social_id;
                $dataClient['first_name'] = $first_name;
                $dataClient['last_name'] = $last_name;
                $dataClient['email'] = $email;

                $pjClientModel = pjClientModel::factory();  
                $ClientId = $pjClientModel->reset()->setAttributes($dataClient)->insert()->getInsertId();
            } else{
                //si el cliente existe, recupera el Id
                $ClientId = $client[0]['id'];
            }
            // retorna el id del cliente(creado o encontrado en la BD)
            return $ClientId;
    }
    
    public function generarOrden($owner_id, $client_id, $price, $total_tax, $payment_interface, $notes, $product_id, $total, $deposit) {
        
        $uuid = str_pad($owner_id,3,"0",STR_PAD_LEFT).uniqid(); 
        $dataOrder = array();
	$dataOrder['uuid'] = $uuid;
        $dataOrder['owner_id'] = $owner_id;
        $dataOrder['client_id'] = $client_id;
        
	    // Esto hay que adaptarlo para que tome todos los productos de un carro
        $dataOrder['total'] = $total;
        $dataOrder['price'] = $price;
        $dataOrder['status'] = "pendiente";
        $dataOrder['payment_method_id'] = 1;    // <-   En DURO Webpay = 1, esto debiera ser seleccionable por el usuario o por el cliente.
        $dataOrder['payment_interface_id'] = $payment_interface;
        $dataOrder['deposit'] = $deposit;
       
        $dataOrder['notes'] = $notes;
        $dataOrder['tax'] = $total_tax;
        
          # Registra IP creación de order
        
        $infoGeoIp = pjTransaction::getInfoIP();
                                
        if (sizeof($infoGeoIp)>0) {
            
            $dataOrder['ip_city_created'] = $infoGeoIp['city'];
            $dataOrder['ip_country_created'] = $infoGeoIp['country'];
            $dataOrder['ip_lat_created'] = $infoGeoIp['lat'];
            $dataOrder['ip_lon_created'] = $infoGeoIp['lon'];
            $dataOrder['ip_geo_created'] = $infoGeoIp['query'];   

        }
       
        $contracts_owner_arr = pjContractPlanModel::factory()
                        ->select('t1.*, t2.name as plan')
                        ->join('pjPlan', 't1.plan_id=t2.id', 'inner')
                        ->where('t1.owner_id', $owner_id)
                        ->orderBy('t1.id DESC')->limit(1)->findAll()->getData();
        
       // error_log("Array" . print_r($contracts_owner_arr, TRUE));
        
        if (sizeof($contracts_owner_arr) == 1) {
            $dataOrder['contract_plan_id'] = $contracts_owner_arr[0]['id'];
        }
        // Registra Order
        $pjOrderModel = pjOrderModel::factory();    
        $OrderId = $pjOrderModel->reset()->setAttributes($dataOrder)->insert()->getInsertId();
	// Items data
        $dataOrderItems  = array('order_id' => $OrderId, 'product_id'=> $product_id, 'price'=> $price, 'tax'=> $total_tax, 'total'=> $total);
		
	$pjOrderItemModel = pjOrderItemModel::factory();    
        $ItemId = $pjOrderItemModel->reset()->setAttributes($dataOrderItems)->insert()->getInsertId();
        
        // New Realic 
        
         if (extension_loaded('newrelic')) { // Ensure PHP agent is available 
            
             $arr = pjOrderModel::factory()
                            ->select('t1.*, round(t1.total,0) as monto_vta, t2.first_name as client_first_name, t2.last_name as client_last_name,  t3.uuid as payment_interface_uuid, t3.name as payment_interface_name, t3.type as payment_interface_type, t4.description as payment_method_description')
                            ->join('pjClient', 't1.client_id=t2.id', 'left')
                            ->join('pjPaymentInterface', 't1.payment_interface_id=t3.id', 'left')
                            ->join('pjPaymentMethod', 't1.payment_method_id=t4.id', 'left')    
                            ->where('t1.id ='.$OrderId)
                            ->limit(1)
                            ->findAll()
                            ->getData();
                     
             newrelic_record_custom_event("Ordenes", $arr[0]);
         }
         
         ///////
		
	return $OrderId;
    }
    public function viewError() {
        $error_code = $_GET['error'];
        $error_type = $_GET['errorType'];
        

        
        $errors = pjErrorMessageModel::factory()
                    ->select('t1.*')
                    ->where('t1.error_code', $error_code)
                    ->where('t1.error_type', $error_type)
                    ->findAll()    
                    ->getData();
        
        
        //$error_message = $errors[0]["error_message"];
        
        error_log("aasdas".print_r($errors,TRUE));
        
        $this->setLayout('pjActionPayment'); 
        $this->set('error', $errors[0]['error_message']);
        
    }
    
    public function testLogger(){
        
        $logger = new pjTransaction();
        $logger->logger2("5874", LOGGER_INFO, "Test de logger 2", "T");
        
        pjTransaction::logger("5874", LOGGER_INFO, "Test de logger", "T");
        
        
        $logger = new pjAdminPaymentRequest();
        $logger->logger2("5874", LOGGER_INFO, "Test de logger PaymentRequest", "T");
        
        
    }
    
    public function doCheckOutProduct(){
        if (isset($_POST['owner_id']) && isset($_POST['product_id'])) { 
            $this->setLayout('pjActionDoCheckOut');
            
            $owner_id = $_POST['owner_id'];
            $cantidad = $_POST['q'];
            $product_id = $_POST['product_id'];
            
            
            //VALIDA QUE EL PRODUCTO TENGA STOCK PARA LA VENTA
            if(!pjTransaction::haveAvailableStock($product_id,$cantidad)){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=2&error=0");
            }
            
            
            

            //VALIDA DE QUE EXISTA EL OWNER Y ESTE ACTIVO
            if(!pjTransaction::isOwnerActive($owner_id)){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=2&error=1");
            }
            
            //VALIDA QUE EL PRODUCTO ESTE ACTIVO Y ADEMAS SE PUEDA VENDER
            if(!pjTransaction::isProductActive($product_id)){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=2&error=2");
            }

            $owners = pjOwnerModel::factory()->where('id',$owner_id)->findAll()->getData();
            
            $products = pjProductModel::factory()
                    ->select("t1.*, ROUND(t1.base_price*(100+t1.tax)/100) as total")
                    ->where('t1.id = ', $product_id)
                    ->where('t1.owner_id = ',$owner_id)    
                    ->findAll()
                    ->getData();
            
            $regiones = pjRegionModel::factory()->findAll()->getData();
            
            
            $this->set('owner', $owners[0]);
            $this->set('product', $products[0]);
            $this->set('cantidad', $cantidad);
            $this->set('total', $products[0]['total'] * $cantidad); 
            
            $this->set('regiones', $regiones);
       
            
        }    
        else{
            pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=2&error=3");
        }
    }
    public function doCheckOutProductFinal(){
        $this->setLayout('pjActionDoCheckOut');
        
        if (isset($_POST['product_id']) && isset($_POST['owner_id']) && isset($_POST['cantidad']) && isset($_POST['email']) && isset($_POST['social_id']) && isset($_POST['first_name']) && isset($_POST['last_name']) ) {
           
            error_log("Iniciando  doCheckOutProductFinal ...");
            //OBTENER DATOS DESDE LA VISTA
            $owner_id = $_POST['owner_id'];
            $product_id = $_POST['product_id'];
            $cantidad = $_POST['cantidad'];
            
            //VALIDA DE QUE EXISTA EL OWNER Y ESTE ACTIVO
            if(!pjTransaction::isOwnerActive($owner_id)){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=0");
            }
            
            //VALIDA QUE EL PRODUCTO ESTE ACTIVO Y ADEMAS SE PUEDA VENDER
            if(!pjTransaction::isProductActive($product_id)){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=1");
            }
            
          
            
            $email = $_POST['email'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $social_id = $_POST['social_id'];
            
            $notes = "";
            $payment_interface = 99999998;
            
            $products = pjProductModel::factory()
                    ->select("t1.*, base_price as price, ROUND(base_price*tax/100) as total_tax , ROUND(t1.base_price*(100+t1.tax)/100) as total")
                    ->where('t1.id = ', $product_id)
                    ->where('t1.owner_id = ',$owner_id)    
                    ->findAll()
                    ->getData();
            
            //OBTIENE LOS MONTOS PARA CREAR LA ORDEN
            $total_tax = $products[0]['total_tax'] * $cantidad;
            $price = $products[0]['price'] * $cantidad; 
            $deposit = $products[0]['total'] * $cantidad;
            $monto = $products[0]['total'] * $cantidad;
            
            //CREAR U OBTENER CLIENTE
            $client_id = $this->getClientId($owner_id, $email, $first_name, $last_name, $social_id);
            if (sizeof($client_id) == 0) {
                 pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=2");
            }
            
            error_log("Clien_id ..." . $client_id);
            
            $order_id = $this->createOrderFromProduct($owner_id, $client_id, $monto, $total_tax, $payment_interface, $notes, $product_id, $price, $deposit, $cantidad);
            pjTransaction::logger($order_id, LOGGER_INFO, "Orden de pago creada desde página de producto/servicio." , 'T');
            
            error_log("Order id ... " . $order_id);
            
            $order_uuid = pjOrderModel::factory()->where('id',$order_id)->findAll()->getData();
            
            if (sizeof($order_uuid) == 0) {
                 pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=3");
            }
            
            //RECUPERA DIRECCION DE DESPACHO EN CASO DE QUE SEA ENVIADO CON SHIPPING_ADDRESS = T 
            if (isset($_POST['shipping_address'])) {
                
                $shipping_address = $_POST['shipping_address'];
                $region_id = $_POST['region_id'];
                $comuna_id = $_POST['comuna_id'];
                
                error_log("Parámetros : " . $shipping_address . " / " . $region_id . " / " . $comuna_id . " / " . $order_id);
                
                $this->agregaDireccionDespacho($order_id, $shipping_address, $region_id, $comuna_id);
                
            }
            
            // Suscripción mailChimp
		 if ((isset($_POST['suscribe']) ) && ($_POST['suscribe']=='S')) {
                     
                       $owner = pjOwnerModel::factory()->find($owner_id)->getData();
		
                        try {
                                error_log("pjFront.doCheckOutProductFinal > Intentando suscripcion MailChimp");

                                $apikey=$owner['mailchimp_api_key'];
                                $listId=$owner['mail_chimp_list_id'];

                                $mailchimp = new MCAPI($apikey,true);

                                $merge_vars = array('FNAME'=>$first_name.' '.$last_name);

                                $resultado = $mailchimp->listSubscribe( $listId, $_POST['email'], $merge_vars );

                                //Controlamos y registramos el resultado
                                if ($mailchimp->errorCode)
                                {
                                    error_log("pjFront.doCheckOutProductFinal > ERR MailChimp Suscription info: Owner = " .$owner['id'] . " E-Mail = ".$email . " Code = " . $mailchimp->errorCode . " Msg = " . $mailchimp->errorMessage);
                                } 
                                else 
                                {
                                   error_log("pjFront.doCheckOutProductFinal > OK MailChimp Suscription info: Owner = " . $owner['id'] . " E-Mail = " . $email);
                                }
                        } catch (Exception $e) {
                                error_log("pjFront.doCheckOutProductFinal > ERR MailChimp General Suscripcion MailChimp: " . $e->getMessage() . "\n" );
                        }
                        
		  }
            
            $this->setLayout('pjActionPayment'); 
            $this->set('forward', $this->pjActionProcessDoPay($order_uuid[0]["uuid"]));
             
        }else{
            pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=4");
        }
    }
    
    public function agregaDireccionDespacho($order_id, $shipping_address, $region_id, $comuna_id) {
        
        $pjShippingAddress = pjOrderShippingAddress::factory();
        
        $data = array();
        $data['order_id'] = $order_id;
        $data['address'] = $shipping_address;
        $data['region_id'] = $region_id;
        $data['comuna_id'] = $comuna_id;
        
        error_log("Call insert with : " . print_r($data, true));
        
        $id = $pjShippingAddress->reset()->setAttributes($data)->insert()->getInsertId();
        
        error_log("Row ID : " . $id);
        
        return $id;
        
    }
    
    public function createOrderFromProduct($owner_id, $client_id, $total, $total_tax, $payment_interface, $notes, $product_id, $price, $deposit, $cantidad) {
        
        $products = pjProductModel::factory()
            ->select("t1.*, base_price as price, ROUND(base_price*tax/100) as total_tax , ROUND(t1.base_price*(100+t1.tax)/100) as total")
            ->where('t1.id = ', $product_id)
            ->where('t1.owner_id = ',$owner_id)    
            ->findAll()
            ->getData();
        
        $uuid = str_pad($owner_id,3,"0",STR_PAD_LEFT).uniqid(); 
        $dataOrder = array();
	$dataOrder['uuid'] = $uuid;
        $dataOrder['owner_id'] = $owner_id;
        $dataOrder['client_id'] = $client_id;
        
	    // Esto hay que adaptarlo para que tome todos los productos de un carro
        $dataOrder['total'] = $total;
        $dataOrder['price'] = $price;
        $dataOrder['status'] = "pendiente";
        $dataOrder['payment_method_id'] = 1;    // <-   En DURO Webpay = 1, esto debiera ser seleccionable por el usuario o por el cliente.
        $dataOrder['payment_interface_id'] = $payment_interface;
        $dataOrder['deposit'] = $deposit;
       
        $dataOrder['notes'] = $notes;
        $dataOrder['tax'] = $total_tax;
       
        $contracts_owner_arr = pjContractPlanModel::factory()
                        ->select('t1.*, t2.name as plan')
                        ->join('pjPlan', 't1.plan_id=t2.id', 'inner')
                        ->where('t1.owner_id', $owner_id)
                        ->orderBy('t1.id DESC')->limit(1)->findAll()->getData();
        
        if (sizeof($contracts_owner_arr) == 1) {
            $dataOrder['contract_plan_id'] = $contracts_owner_arr[0]['id'];
        }
        // Registra Order
        $pjOrderModel = pjOrderModel::factory();    
        $OrderId = $pjOrderModel->reset()->setAttributes($dataOrder)->insert()->getInsertId();
	// Items data
        $dataOrderItems  = array('order_id' => $OrderId, 'product_id'=> $product_id, 'price'=> $products[0]['price'], 'tax'=> $products[0]['total_tax'], 'total'=> $products[0]['total'], 'units'=>$cantidad);
		
	$pjOrderItemModel = pjOrderItemModel::factory();    
        $ItemId = $pjOrderItemModel->reset()->setAttributes($dataOrderItems)->insert()->getInsertId();
        
         // New Realic 
        
         if (extension_loaded('newrelic')) { // Ensure PHP agent is available 
            
             $arr = pjOrderModel::factory()
                            ->select('t1.*, round(t1.total,0) as monto_vta, t2.first_name as client_first_name, t2.last_name as client_last_name,  t3.uuid as payment_interface_uuid, t3.name as payment_interface_name, t3.type as payment_interface_type, t4.description as payment_method_description')
                            ->join('pjClient', 't1.client_id=t2.id', 'left')
                            ->join('pjPaymentInterface', 't1.payment_interface_id=t3.id', 'left')
                            ->join('pjPaymentMethod', 't1.payment_method_id=t4.id', 'left')    
                            ->where('t1.id ='.$OrderId)
                            ->limit(1)
                            ->findAll()
                            ->getData();
                     
             newrelic_record_custom_event("Ordenes", $arr[0]);
         }
         
         ///////
		
	return $OrderId;
    }
    
    public function doCompleteInfoBuyer(){
        if (isset($_POST['uuid'])) { 
            $this->setLayout('pjActionDoCompleteInfoBuyer');
            
            $vpos_uuid = $_POST['uuid'];

            $products = pjTransaction::getProductsByVPOS($vpos_uuid);
            //al listado de productos se le agrega la cantidad y el total
            $products = pjTransaction::getQuantiTyByProducts($products, $_POST);
            $total = pjTransaction::getTotalByProducts($products);
            
            $owner = pjTransaction::getOwnerbyVPOS($vpos_uuid);
            //VALIDA DE QUE EXISTA EL OWNER Y ESTE ACTIVO
            if(!pjTransaction::isOwnerActive($owner['id'])){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=2&error=1");
            }
            
            
            $paymentInterfaces = pjTransaction::getPaymentInterface($vpos_uuid);
            $paymentInterface = $paymentInterfaces[0];
            
            $arr_pi = pjPaymentInterfaceModel::factory()
			        ->select('t2.name as owner_name, t2.id as owner_id, t2.mail_chimp_active, t2.mailchimp_api_key, 	mail_chimp_list_id')
			        ->join('pjOwner', 't1.owner_id=t2.id', 'left')
			        ->where('t1.uuid = ', $_POST["uuid"])  
				->where('t1.is_active', 'T') 
			        ->findAll()
			        ->getData();
            
            $this->set('pi_data', $arr_pi);
            
            $this->set('owner', $owner);
            $this->set('products', $products);
            $this->set('uuid', $vpos_uuid);
            $this->set('total', $total);
            $this->set("request_extra_data",$paymentInterface['request_extra_data']);
            
        }    
        else{
            pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=2&error=3");
        }
    }
    
    public function doCheckOut(){
        $this->setLayout('pjActionDoCheckOut');
        
        if (isset($_POST['uuid']) && isset($_POST['email']) && isset($_POST['social_id']) && isset($_POST['first_name']) && isset($_POST['last_name']) ) {
           
            $vpos_uuid = $_POST['uuid'];
             
            //OBTIENE EL LISTADO DE PRODUCTOS DEL FORMULARIO DE PAGO 
            $products = pjTransaction::getProductsByVPOS($vpos_uuid);
            
            //al listado de productos se le agrega la cantidad y el total
            $products = pjTransaction::getQuantiTyByProducts($products, $_POST);
            $total = pjTransaction::getTotalByProducts($products); 
             
            $owner = pjTransaction::getOwnerbyVPOS($vpos_uuid);
            
            //VALIDA DE QUE EXISTA EL OWNER Y ESTE ACTIVO
            if(!pjTransaction::isOwnerActive($owner['id'])){
                pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=0");
            }
            
            //obtiene el id de la payment interface
            $payment_interfaces = pjTransaction::getPaymentInterface($vpos_uuid);
            $payment_interface_id = $payment_interfaces[0]['id'];
            
            $email = $_POST['email'];
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $social_id = $_POST['social_id'];
            
            if($payment_interfaces[0]['request_extra_data']=='T'){
               $notes = "Nombre : " . $_POST['first_name_extra_data']."<br>"
                       ." Email : " . $_POST['email_extra_data']."<br>"
                       ." Mensaje : " . $_POST['mensaje_extra_data'];
            }else{
               $notes = ""; 
            }
            

            
            
            
            //OBTIENE LOS MONTOS PARA CREAR LA ORDEN
            
            $totals = pjTransaction::getAmountByProducts($products);
            
            //CREAR U OBTENER CLIENTE
            $client_id = $this->getClientId($owner['id'], $email, $first_name, $last_name, $social_id);
            
            if (sizeof($client_id) == 0) {
                 pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=2");
            }
            
            $order_id = pjTransaction::createOrderFromProducts($owner['id'], $client_id, $totals['total_monto'], $totals['total_tax'], $totals['total_deposit'], $totals['total_price'], $payment_interface_id, $notes, $products);

            pjTransaction::logger($order_id, LOGGER_INFO, "Orden de pago creada desde Formulario de pagos :".$vpos_uuid , 'T');
            
            $order_uuid = pjOrderModel::factory()->where('id',$order_id)->findAll()->getData();
            
            if (sizeof($order_uuid) == 0) {
                 pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=3");
            }
            
            // Suscripción mailChimp
		 if ((isset($_POST['suscribe']) ) && ($_POST['suscribe']=='S')) {
		
                        try {
                                error_log("pjFront.doCheckOut > Intentando suscripcion MailChimp");

                                $apikey=$owner['mailchimp_api_key'];
                                $listId=$owner['mail_chimp_list_id'];

                                $mailchimp = new MCAPI($apikey,true);

                                $merge_vars = array('FNAME'=>$first_name.' '.$last_name);

                                $resultado = $mailchimp->listSubscribe( $listId, $_POST['email'], $merge_vars );

                                //Controlamos y registramos el resultado
                                if ($mailchimp->errorCode)
                                {
                                    error_log("pjFront.doCheckOut > ERR MailChimp Suscription info: Owner = " .$owner['id'] . " E-Mail = ".$email . " Code = " . $mailchimp->errorCode . " Msg = " . $mailchimp->errorMessage);
                                } 
                                else 
                                {
                                   error_log("pjFront.doCheckOut > OK MailChimp Suscription info: Owner = " . $owner['id'] . " E-Mail = " . $email);
                                }
                        } catch (Exception $e) {
                                error_log("pjFront.doCheckOut > ERR MailChimp General Suscripcion MailChimp: " . $e->getMessage() . "\n" );
                        }
                        
		  }
            
            $this->setLayout('pjActionPayment'); 
            $this->set('forward', $this->pjActionProcessDoPay($order_uuid[0]["uuid"]));
             
        }else{
            pjUtil::redirect(PJ_INSTALL_URL."index.php?controller=pjFront&action=viewError&errorType=3&error=4");
        }
    }
    
}
?>
