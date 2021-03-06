<?php


/**
 * UserAjax
 *
 * @category    Erdiko
 * @package     User
 * @copyright   Copyright (c) 2016, Arroyo Labs, http://www.arroyolabs.com
 * @author      Leo Daidone, leo@arroyolabs.com
 */

namespace erdiko\users\controllers;

use erdiko\authenticate\BasicAuth;
use erdiko\authenticate\iErdikoUser;
use erdiko\authorize\Authorizer;
use erdiko\users\models\User;

class UserAjax extends \erdiko\core\AjaxController
{
	private $id = null;
	/**
	 * @param $action
	 * @param $resource
	 *
	 * @return bool
	 */
	protected function checkAuth($action,$resource)
	{
		return true; // remove after testing
		try {
			$userModel  = new User();
			$auth       = new BasicAuth($userModel);
			$user       = $auth->current_user();
			if($user instanceof iErdikoUser){
				$authorizer = new Authorizer( $user );
				$result     = $authorizer->can( $action, $resource );
			} else {
				$result = false;
			}
		} catch (\Exception $e) {
			\error_log($e->getMessage());
			$result = false;
		}
		return $result;
	}

	/**
	 * @param null $var
	 *
	 * @return mixed
	 */
	public function get($var = null)
	{
		$this->id = 0;
		if (!empty($var)) {
			$routing = explode('/', $var);
			if(is_array($routing)) {
				$var = array_shift($routing);
				$this->id = empty($routing)
					? 0
					: array_shift($routing);
			} else {
				$var = $routing;
			}

			if ($this->checkAuth("read",$var)) {
				// load action based off of naming conventions
				return $this->_autoaction($var, 'get');
			} else {
				return $this->getForbbiden($var);
			}
		} else {
			return $this->getNoop();
		}
	}

	/**
	 * @param null $var
	 *
	 * @return mixed
	 */
	public function post($var = null)
	{
		$this->id = 0;
		if (!empty($var)) {
			$routing = explode('/', $var);
			if(is_array($routing)) {
				$var = array_shift($routing);
				$this->id = empty($routing)
					? 0
					: array_shift($routing);
			} else {
				$var = $routing;
			}

			if ($this->checkAuth("write", $var)) {
				// load action based off of naming conventions
				return $this->_autoaction($var, 'post');
			} else {
				return $this->getForbbiden($var);
			}
		} else {
			return $this->getNoop();
		}
	}

	/**
	 * Default response for not Authorized requests
	 */
	protected function getForbbiden($var)
	{
		$response = array(
			"action" => $var,
			"success" => false,
			"error_code" => 403,
			"error_message" => "Sorry, you don't have permission for this action"
		);

		$this->setContent($response);
	}

	/**
	 * Default response for no action requests
	 */
	protected function getNoop()
	{
		$response = array(
			"action" => "None",
			"success" => false,
			"error_code" => 404,
			"error_message" => 'Sorry, you need to specify a valid action'
		);

		$this->setContent($response);
	}


	/**
	 * User CRUD actions
	 */
	public function postRegister()
	{
		$response = array(
			"method" => "register",
			"success" => false,
			"user" => "",
			"error_code" => 0,
			"error_message" => ""
		);

		try {
			$data = json_decode(file_get_contents("php://input"));
            // Check required fields
            $requiredParams = array('email','password', 'role', 'name');
            $params = (array) $data;
            foreach ($requiredParams as $param){
                if(empty($params[$param])){
                    throw new \Exception(ucfirst($param) .' is required.');
                }
            }

			$userModel = new User();
			$userId = $userModel->save($data);
            if(empty($userId)){
                throw  new \Exception('Could not create new user.');
            }
            $user = $userModel->getById($userId);
            $output = array('id'       => $user->getId(),
                            'email'    => $user->getEmail(),
                            'password' => $user->getPassword(),
                            'role'     => $user->getRole(),
                            'name'     => $user->getName(),
                            'last_login' => $user->getLastLogin(),
                            'gateway_customer_id'=> $user->getGatewayCustomerId()
            );

			$response['user'] = $output;
			$response['success'] = true;
			$this->setStatusCode(200);
		} catch (\Exception $e) {
			$response['error_message'] = $e->getMessage();
			$response['error_code'] = $e->getCode();
		}

		$this->setContent($response);
	}

	public function getRead()
	{
		$response = array(
			"method" => "read",
			"success" => false,
			"body" => "",
			"error_code" => 0,
			"error_message" => ""
		);

		try {
			$user = new User();
			$result = array();
			if(empty($this->id) || ($this->id < 1)){
				$params = json_decode(file_get_contents("php://input"));
				if(empty($params)) {
					// List all users
					$users = $user->getUsers();
					foreach ( $users as $item ) {
						array_push( $result, $item->marshall( 'array' ) );
					}
				}else{
					$users = $user->getByParams($params);
					foreach ( $users as $item ) {
						array_push( $result, $item->marshall( 'array' ) );
					}
				}
			} else {
				// Get User by ID
				$users = $user->getById($this->id);
				$result = empty($users) ? null : $users->marshall('array');
			}

			$response['success'] = true;
			$response['body'] = $result;

			$this->setStatusCode(200);
		} catch (\Exception $e) {
			$response['error_message'] = $e->getTraceAsString();
			$response['error_code'] = $e->getCode();
		}

		$this->setContent($response);
	}

	public function getGetusers(){
        $response = array(
            "method" => "getusers",
            "success" => false,
            "users" => "",
            "error_code" => 0,
            "error_message" => ""
        );


        try {
            $userModel = new User();
            $users = $userModel->getUsers();
            $output = array();
            foreach ($users as $user){
                $output[] = array('id'       => $user->getId(),
                                  'email'    => $user->getEmail(),
                                  'password' => $user->getPassword(),
                                  'role'     => $user->getRole(),
                                  'name'     => $user->getName(),
                                  'last_login' => $user->getLastLogin(),
                                  'gateway_customer_id'=> $user->getGatewayCustomerId()
                );
            }
            $response['success'] = true;
            $response['users'] = $output;
            $this->setStatusCode(200);
        } catch (\Exception $e) {
            $response['error_message'] = $e->getMessage();
            $response['error_code'] = $e->getCode();
        }

        $this->setContent($response);

    }

    public function getGetUser(){
        $response = array(
            "method" => "getuser",
            "success" => false,
            "user" => "",
            "error_code" => 0,
            "error_message" => ""
        );

        try {
            $params = (object) $_REQUEST;
            // Check required fields
            if((empty($this->id) || ($this->id < 1)) && (empty($params->id) || ($params->id < 1))){
                throw new \Exception("ID is required.");
            } elseif (empty($params->id) && (!empty($this->id) || ($this->id >= 1))) {
                $params->id = $this->id;
            }

            $userModel = new User();
            $user = $userModel->getById($params->id);
            if(empty($user)){
                throw new \Exception('User not found.');
            }
            $output = array('id'       => $user->getId(),
                              'email'    => $user->getEmail(),
                              'password' => $user->getPassword(),
                              'role'     => $user->getRole(),
                              'name'     => $user->getName(),
                              'last_login' => $user->getLastLogin(),
                              'gateway_customer_id'=> $user->getGatewayCustomerId()
            );
            $response['success'] = true;
            $response['user'] = $output;
            $this->setStatusCode(200);
        } catch (\Exception $e) {
            $response['error_message'] = $e->getMessage();
            $response['error_code'] = $e->getCode();
        }

        $this->setContent($response);
    }

	public function postUpdate()
	{
		$response = array(
			"method" => "update",
			"success" => false,
			"user" => "",
			"error_code" => 0,
			"error_message" => ""
		);

		try {
			$params = json_decode(file_get_contents("php://input"));

			// Check required fields
			if((empty($this->id) || ($this->id < 1)) && (empty($params->id) || ($params->id < 1))){
				throw new \Exception("Id is required.");
			} elseif (empty($params->id) && (!empty($this->id) || ($this->id >= 1))) {
				$params->id = $this->id;
			}

			$userModel = new User();
			$entity = $userModel->getById($params->id);
            if(empty($entity)){
                throw new \Exception('User not found.');
            }
            $result = $userModel->save($params);
            $user = $userModel->getById($result);
            $output = array('id'       => $user->getId(),
                            'email'    => $user->getEmail(),
                            'password' => $user->getPassword(),
                            'role'     => $user->getRole(),
                            'name'     => $user->getName(),
                            'last_login' => $user->getLastLogin(),
                            'gateway_customer_id'=> $user->getGatewayCustomerId()
            );
			$response['success'] = true;
			$response['user'] = $output;
			$this->setStatusCode(200);
		} catch (\Exception $e) {
			$response['error_message'] = $e->getMessage();
			$response['error_code'] = $e->getCode();
		}

		$this->setContent($response);
	}

	public function getCancel()
	{
		$response = array(
			"method" => "cancel",
			"success" => false,
			"user" => "",
			"error_code" => 0,
			"error_message" => ""
		);

		try {

            $params = (object) $_REQUEST;
            // Check required fields
            if((empty($this->id) || ($this->id < 1)) && (empty($params->id) || ($params->id < 1))){
                throw new \Exception("Id is required.");
            } elseif (empty($params->id) && (!empty($this->id) || ($this->id >= 1))) {
                $params->id = $this->id;
            }

			$userModel = new User();
			$result = $userModel->deleteUser($params->id);

            if(false == $result){
                throw new \Exception('User could not be deleted.');
            }

			$response['user'] = array('id' => $params->id);
			$response['success'] = true;

			$this->setStatusCode(200);
		} catch (\Exception $e) {
			$response['error_message'] = $e->getMessage();
			$response['error_code'] = $e->getCode();
		}

		$this->setContent($response);
	}
}