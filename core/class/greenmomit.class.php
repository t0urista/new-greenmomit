<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

define('GREENMOMITADDR', 'https://apist.greenmomit.com:8443/momitst/webserviceapi');

class greenmomit extends eqLogic {
	/*     * *************************Attributs****************************** */

	private static $_sessionToken = null;
	public static $_widgetPossibility = array('custom' => true);

	/*     * ***********************Methode static*************************** */

	public static function cron15() {
		foreach (greenmomit::byType('greenmomit') as $eqLogic) {
			if ($eqLogic->getIsEnable() == 1) {
				try {
					$eqLogic->syncData();
					if ($eqLogic->getConfiguration('greenmomitNumberFailed', 0) > 0) {
						$eqLogic->setConfiguration('greenmomitNumberFailed', 0);
						$eqLogic->save();
					}
				} catch (Exception $e) {
					if ($eqLogic->getConfiguration('greenmomitNumberFailed', 0) > 3) {
						log::add('greenmommit', 'error', __('Erreur sur ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $e->getMessage());
					} else {
						$eqLogic->setConfiguration('greenmomitNumberFailed', $eqLogic->getConfiguration('greenmomitNumberFailed', 0) + 1);
						$eqLogic->save();
					}
				}
			}
		}
	}

	public static function cronDaily() {
		sleep(60);
		foreach (greenmomit::byType('greenmomit') as $eqLogic) {
			if ($eqLogic->getIsEnable() == 1 && $eqLogic->getConfiguration('controlByGreenmomit', 0) == 0) {
				$manuel = $eqLogic->getCmd(null, 'manuel');
				$manuel->execCmd();
				$order = $eqLogic->getCmd(null, 'order');
				$thermostat = $eqLogic->getCmd(null, 'thermostat');
				$thermostat->execCmd(array('slider' => $order->execCmd()));
			}
		}
	}

	public static function connectApi() {
		$request_http = new com_http(GREENMOMITADDR . '/user/connectApi?email=' . urlencode(config::byKey('username', 'greenmomit')) . '&clientId=' . config::byKey('clientId', 'greenmomit'));
		$request_http->setPost(
			array(
				'email' => config::byKey('username', 'greenmomit'),
				'client_id' => config::byKey('clientId', 'greenmomit'),
			)
		);
		$result = json_decode(trim($request_http->exec()), true, 512, JSON_BIGINT_AS_STRING);
		if ($result['result'] != 200) {
			throw new Exception('Error on connectApi : ' . $result['error']);
		}
		return $result;
	}

	public static function loginApi() {
		if (self::$_sessionToken != null) {
			return self::$_sessionToken;
		}

		$connectApi = self::connectApi();
		$request_http = new com_http(GREENMOMITADDR . '/user/loginApi?loginToken=' . $connectApi['data']['loginToken'] . '&password=' . urlencode(config::byKey('password', 'greenmomit')) . '&secretKey=' . config::byKey('secretKey', 'greenmomit'));
		$request_http->setPost(
			array(
				'loginToken' => $connectApi['data']['loginToken'],
				'password' => config::byKey('password', 'greenmomit'),
				'secretKey' => config::byKey('secretKey', 'greenmomit'),
			)
		);
		$result = json_decode(trim($request_http->exec()), true, 512, JSON_BIGINT_AS_STRING);
		self::$_sessionToken = $result;
		return $result;
	}

	public static function userGet() {
		$loginApi = self::loginApi();
		$request_http = new com_http(GREENMOMITADDR . '/user/' . $loginApi['data']['sessionToken']);
		return json_decode(trim($request_http->exec()), true, 512, JSON_BIGINT_AS_STRING);
	}

	public static function userThermostatsGet() {
		$loginApi = self::loginApi();
		$request_http = new com_http(GREENMOMITADDR . '/user/' . $loginApi['data']['sessionToken'] . '/thermostats');
		return json_decode(trim($request_http->exec()), true, 512, JSON_BIGINT_AS_STRING);
	}

	public static function syncWithGreenMomit() {
		$thermostats = self::userThermostatsGet();
		foreach ($thermostats['datas'] as $thermostat) {
			$eqLogic = greenmomit::byLogicalId($thermostat['id'], 'greenmomit');
			if (!is_object($eqLogic)) {
				$eqLogic = new greenmomit();
				$eqLogic->setName($thermostat['name']);
				$eqLogic->setEqType_name('greenmomit');
				$eqLogic->setIsVisible(1);
				$eqLogic->setIsEnable(1);
				$eqLogic->setCategory('heating', 1);
				$eqLogic->setLogicalId($thermostat['id']);
				$eqLogic->save();
			}

			$refresh = $eqLogic->getCmd(null, 'refresh');
			if (!is_object($refresh)) {
				$refresh = new greenmomitCmd();
				$refresh->setLogicalId('refresh');
				$refresh->setIsVisible(1);
				$refresh->setName(__('Rafraîhir', __FILE__));
			}
			$refresh->setType('action');
			$refresh->setSubType('other');
			$refresh->setEqLogic_id($eqLogic->getId());
			$refresh->save();

			$temperature = $eqLogic->getCmd(null, 'temperature');
			if (!is_object($temperature)) {
				$temperature = new greenmomitCmd();
				$temperature->setLogicalId('temperature');
				$temperature->setIsVisible(1);
				$temperature->setName(__('Température', __FILE__));
				$temperature->setTemplate('dashboard', 'tile');
			}
		$temperature->setDisplay('generic_type', 'THERMOSTAT_TEMPERATURE');
			$temperature->setUnite('°C');
			$temperature->setType('info');
			$temperature->setSubType('numeric');
			$temperature->setEqLogic_id($eqLogic->getId());
			$temperature->save();

			$humidity = $eqLogic->getCmd(null, 'humidity');
			if (!is_object($humidity)) {
				$humidity = new greenmomitCmd();
				$humidity->setLogicalId('humidity');
				$humidity->setIsVisible(1);
				$humidity->setName(__('Humidité', __FILE__));
				$humidity->setTemplate('dashboard', 'tile');
			}
			$humidity->setType('info');
			$humidity->setSubType('numeric');
			$humidity->setUnite('%');
			$humidity->setEqLogic_id($eqLogic->getId());
			$humidity->save();

			$order = $eqLogic->getCmd(null, 'order');
			if (!is_object($order)) {
				$order = new greenmomitCmd();
				$order->setLogicalId('order');
				$order->setIsVisible(0);
				$order->setName(__('Ordre', __FILE__));
				$order->setTemplate('dashboard', 'tile');
			}
			$order->setDisplay('generic_type', 'THERMOSTAT_SETPOINT');
			$order->setType('info');
			$order->setSubType('numeric');
			$order->setEqLogic_id($eqLogic->getId());
			$order->setUnite('°C');
			$order->save();

			$thermostat = $eqLogic->getCmd(null, 'thermostat');
			if (!is_object($thermostat)) {
				$thermostat = new greenmomitCmd();
				$thermostat->setLogicalId('thermostat');
				$thermostat->setIsVisible(1);
				$thermostat->setName(__('Thermostat', __FILE__));
				$thermostat->setTemplate('dashboard', 'thermostat');	 
				$thermostat->setTemplate('mobile', 'thermostat');
			}
			$thermostat->setDisplay('generic_type', 'THERMOSTAT_SET_SETPOINT');
			$thermostat->setValue($order->getId());
			$thermostat->setType('action');
			$thermostat->setSubType('slider');
			$thermostat->setConfiguration('minValue', 12);
			$thermostat->setConfiguration('maxValue', 28);
			$thermostat->setEqLogic_id($eqLogic->getId());
			$thermostat->setUnite('°C');
			$thermostat->save();

			$mode = $eqLogic->getCmd(null, 'mode');
			if (!is_object($mode)) {
				$mode = new greenmomitCmd();
				$mode->setLogicalId('mode');
				$mode->setIsVisible(0);
				$mode->setName(__('Mode', __FILE__));
			}
			$mode->setType('info');
			$mode->setSubType('numeric');
			$mode->setEqLogic_id($eqLogic->getId());
			$mode->save();

			$state = $eqLogic->getCmd(null, 'state');
			if (!is_object($state)) {
				$state = new greenmomitCmd();
				$state->setLogicalId('state');
				$state->setIsVisible(1);
				$state->setName(__('Etat', __FILE__));
				$state->setTemplate('dashboard', 'tile');
			}
			$state->setType('info');
			$state->setSubType('numeric');
			$state->setEqLogic_id($eqLogic->getId());
			$state->save();

			$controlRelay = $eqLogic->getCmd(null, 'controlRelay');
			if (!is_object($controlRelay)) {
				$controlRelay = new greenmomitCmd();
				$controlRelay->setLogicalId('controlRelay');
				$controlRelay->setName(__('Relais controlé par', __FILE__));
				$controlRelay->setIsVisible(0);
				$controlRelay->setTemplate('dashboard', 'tile');
			}
			$controlRelay->setType('info');
			$controlRelay->setSubType('numeric');
			$controlRelay->setEqLogic_id($eqLogic->getId());
			$controlRelay->save();

			$relay = $eqLogic->getCmd(null, 'relay');
			if (!is_object($relay)) {
				$relay = new greenmomitCmd();
				$relay->setLogicalId('relay');
				$relay->setName(__('Relais', __FILE__));
				$relay->setIsVisible(1);
				$relay->setTemplate('dashboard', 'heat');
			}
			$relay->setDisplay('generic_type', 'THERMOSTAT_STATE');   
			$relay->setType('info');
			$relay->setSubType('binary');
			$relay->setEqLogic_id($eqLogic->getId());
			$relay->save();

			$standby = $eqLogic->getCmd(null, 'standby');
			if (!is_object($standby)) {
				$standby = new greenmomitCmd();
				$standby->setLogicalId('standby');
				$standby->setIsVisible(0);
				$standby->setName(__('Vacances', __FILE__));
			}
			$standby->setType('info');
			$standby->setSubType('binary');
			$standby->setEqLogic_id($eqLogic->getId());
			$standby->save();

			$standby_on = $eqLogic->getCmd(null, 'standby_on');
			if (!is_object($standby_on)) {
				$standby_on = new greenmomitCmd();
				$standby_on->setLogicalId('standby_on');
				$standby_on->setIsVisible(0);
				$standby_on->setName(__('Attente on', __FILE__));
			}
			$standby_on->setValue($standby->getId());
			$standby_on->setType('action');
			$standby_on->setSubType('other');
			$standby_on->setEqLogic_id($eqLogic->getId());
			$standby_on->save();

			$standby_off = $eqLogic->getCmd(null, 'standby_off');
			if (!is_object($standby_off)) {
				$standby_off = new greenmomitCmd();
				$standby_off->setLogicalId('standby_off');
				$standby_off->setIsVisible(0);
				$standby_off->setName(__('Attente Off', __FILE__));
			}
			$standby_off->setValue($standby->getId());
			$standby_off->setType('action');
			$standby_off->setSubType('other');
			$standby_off->setEqLogic_id($eqLogic->getId());
			$standby_off->save();

			$smart = $eqLogic->getCmd(null, 'smart');
			if (!is_object($smart)) {
				$smart = new greenmomitCmd();
				$smart->setLogicalId('smart');
				$smart->setIsVisible(0);
				$smart->setName(__('Smart', __FILE__));
			}
			$smart->setType('info');
			$smart->setSubType('binary');
			$smart->setEqLogic_id($eqLogic->getId());
			$smart->save();

			$smart_on = $eqLogic->getCmd(null, 'smart_on');
			if (!is_object($smart_on)) {
				$smart_on = new greenmomitCmd();
				$smart_on->setLogicalId('smart_on');
				$smart_on->setIsVisible(0);
				$smart_on->setName(__('Smart On', __FILE__));
			}
			$smart_on->setValue($smart->getId());
			$smart_on->setType('action');
			$smart_on->setSubType('other');
			$smart_on->setEqLogic_id($eqLogic->getId());
			$smart_on->save();

			$smart_off = $eqLogic->getCmd(null, 'smart_off');
			if (!is_object($smart_off)) {
				$smart_off = new greenmomitCmd();
				$smart_off->setLogicalId('smart_off');
				$smart_off->setIsVisible(0);
				$smart_off->setName(__('Smart Off', __FILE__));
			}
			$smart_off->setValue($smart->getId());
			$smart_off->setType('action');
			$smart_off->setSubType('other');
			$smart_off->setEqLogic_id($eqLogic->getId());
			$smart_off->save();

			$presence = $eqLogic->getCmd(null, 'presence');
			if (!is_object($presence)) {
				$presence = new greenmomitCmd();
				$presence->setLogicalId('presence');
				$presence->setIsVisible(0);
				$presence->setName(__('Presence', __FILE__));
			}
			$presence->setType('info');
			$presence->setSubType('binary');
			$presence->setEqLogic_id($eqLogic->getId());
			$presence->save();

			$presense_on = $eqLogic->getCmd(null, 'presence_on');
			if (!is_object($presense_on)) {
				$presense_on = new greenmomitCmd();
				$presense_on->setLogicalId('presence_on');
				$presense_on->setIsVisible(0);
				$presense_on->setName(__('Pressence On', __FILE__));
			}
			$presense_on->setValue($presence->getId());
			$presense_on->setType('action');
			$presense_on->setSubType('other');
			$presense_on->setEqLogic_id($eqLogic->getId());
			$presense_on->save();

			$presence_off = $eqLogic->getCmd(null, 'presence_off');
			if (!is_object($presence_off)) {
				$presence_off = new greenmomitCmd();
				$presence_off->setLogicalId('presence_off');
				$presence_off->setIsVisible(0);
				$presence_off->setName(__('Presence Off', __FILE__));
			}
			$presence_off->setValue($presence->getId());
			$presence_off->setType('action');
			$presence_off->setSubType('other');
			$presence_off->setEqLogic_id($eqLogic->getId());
			$presence_off->save();

			$ambient = $eqLogic->getCmd(null, 'ambient');
			if (!is_object($ambient)) {
				$ambient = new greenmomitCmd();
				$ambient->setLogicalId('ambient');
				$ambient->setIsVisible(0);
				$ambient->setName(__('Ambiance', __FILE__));
			}
			$ambient->setType('info');
			$ambient->setSubType('binary');
			$ambient->setEqLogic_id($eqLogic->getId());
			$ambient->save();

			$ambient_on = $eqLogic->getCmd(null, 'ambient_on');
			if (!is_object($ambient_on)) {
				$ambient_on = new greenmomitCmd();
				$ambient_on->setLogicalId('ambient_on');
				$ambient_on->setIsVisible(0);
				$ambient_on->setName(__('Ambience On', __FILE__));
			}
			$ambient_on->setValue($ambient->getId());
			$ambient_on->setType('action');
			$ambient_on->setSubType('other');
			$ambient_on->setEqLogic_id($eqLogic->getId());
			$ambient_on->save();

			$ambient_off = $eqLogic->getCmd(null, 'ambient_off');
			if (!is_object($ambient_off)) {
				$ambient_off = new greenmomitCmd();
				$ambient_off->setLogicalId('ambient_off');
				$ambient_off->setIsVisible(0);
				$ambient_off->setName(__('Ambience Off', __FILE__));
			}
			$ambient_off->setValue($ambient->getId());
			$ambient_off->setType('action');
			$ambient_off->setSubType('other');
			$ambient_off->setEqLogic_id($eqLogic->getId());
			$ambient_off->save();

		$state_off = $eqLogic->getCmd(null, 'state_off');
			if (!is_object($state_off)) {
				$state_off = new greenmomitCmd();
				$state_off->setLogicalId('state_off');
				$state_off->setIsVisible(0);
				$state_off->setName(__('Etat Off', __FILE__));
			}
			$state_off->setType('info');
			$state_off->setSubType('binary');
			$state_off->setEqLogic_id($eqLogic->getId());
			$state_off->save();


			$state_manuel = $eqLogic->getCmd(null, 'state_manuel');
			if (!is_object($state_manuel)) {
				$state_manuel = new greenmomitCmd();
				$state_manuel->setLogicalId('state_manuel');
				$state_manuel->setIsVisible(0);
				$state_manuel->setName(__('Etat Manuel', __FILE__));
			}
			$state_manuel->setType('info');
			$state_manuel->setSubType('binary');
			$state_manuel->setEqLogic_id($eqLogic->getId());
			$state_manuel->save();

			$state_auto = $eqLogic->getCmd(null, 'state_auto');
			if (!is_object($state_auto)) {
				$state_auto = new greenmomitCmd();
				$state_auto->setLogicalId('state_auto');
				$state_auto->setIsVisible(0);
				$state_auto->setName(__('Etat Auto', __FILE__));
			}
			$state_auto->setType('info');
			$state_auto->setSubType('binary');
			$state_auto->setEqLogic_id($eqLogic->getId());
			$state_auto->save();


			$off = $eqLogic->getCmd(null, 'off');
			if (!is_object($off)) {
				$off = new greenmomitCmd();
				$off->setLogicalId('off');
				$off->setIsVisible(1);
				$off->setName(__('Off', __FILE__));
			}
			$off->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');
			$off->setValue($state_off->getId());
			$off->setType('action');
			$off->setSubType('other');
			$off->setEqLogic_id($eqLogic->getId());
			$off->save();


			$manuel = $eqLogic->getCmd(null, 'manuel');
			if (!is_object($manuel)) {
				$manuel = new greenmomitCmd();
				$manuel->setLogicalId('manuel');
				$manuel->setIsVisible(1);		 
				$manuel->setName(__('Manuel', __FILE__));
			}
			$manuel->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');			
			$manuel->setValue($state_manuel->getId());					 
			$manuel->setType('action');								
			$manuel->setSubType('other');
			$manuel->setEqLogic_id($eqLogic->getId());
			$manuel->save();


			$auto = $eqLogic->getCmd(null, 'auto');
			if (!is_object($auto)) {
				$auto = new greenmomitCmd();
				$auto->setLogicalId('auto');
				$auto->setIsVisible(1);							
				$auto->setName(__('Auto', __FILE__));					
			}
			$auto->setDisplay('generic_type', 'THERMOSTAT_SET_MODE');			
			$auto->setValue($state_auto->getId());										 
			$auto->setType('action');
			$auto->setSubType('other');
			$auto->setEqLogic_id($eqLogic->getId());
			$auto->save();


			$calibration = $eqLogic->getCmd(null, 'calibration');
			if (!is_object($calibration)) {
				$calibration = new greenmomitCmd();
				$calibration->setLogicalId('calibration');
				$calibration->setIsVisible(0);
				$calibration->setName(__('Calibration', __FILE__));
				$calibration->setTemplate('dashboard', 'tile');
			}
			$calibration->setType('info');
			$calibration->setSubType('numeric');
			$calibration->setUnite('°C');
			$calibration->setEqLogic_id($eqLogic->getId());
			$calibration->save();


			$histeresis = $eqLogic->getCmd(null, 'histeresis');
			if (!is_object($histeresis)) {
				$histeresis = new greenmomitCmd();
				$histeresis->setLogicalId('histeresis');
				$histeresis->setIsVisible(0);
				$histeresis->setName(__('Hysteresis', __FILE__));
				$histeresis->setTemplate('dashboard', 'tile');
			}
			$histeresis->setType('info');
			$histeresis->setSubType('numeric');
			$histeresis->setUnite('°C');
			$histeresis->setEqLogic_id($eqLogic->getId());
			$histeresis->save();


			$minTemperatureHeat = $eqLogic->getCmd(null, 'minTemperatureHeat');
			if (!is_object($minTemperatureHeat)) {
				$minTemperatureHeat = new greenmomitCmd();
				$minTemperatureHeat->setLogicalId('minTemperatureHeat');
				$minTemperatureHeat->setIsVisible(0);
				$minTemperatureHeat->setName(__('Temperature Minimale', __FILE__));
				$minTemperatureHeat->setTemplate('dashboard', 'tile');
			}
			$minTemperatureHeat->setType('info');
			$minTemperatureHeat->setSubType('numeric');
			$minTemperatureHeat->setUnite('°C');
			$minTemperatureHeat->setEqLogic_id($eqLogic->getId());
			$minTemperatureHeat->save();

			$maxTemperatureHeat = $eqLogic->getCmd(null, 'maxTemperatureHeat');
			if (!is_object($maxTemperatureHeat)) {
				$maxTemperatureHeat = new greenmomitCmd();
				$maxTemperatureHeat->setLogicalId('maxTemperatureHeat');
				$maxTemperatureHeat->setIsVisible(0);
				$maxTemperatureHeat->setName(__('Temperature Maximale', __FILE__));
				$maxTemperatureHeat->setTemplate('dashboard', 'tile');
			}
			$maxTemperatureHeat->setType('info');
			$maxTemperatureHeat->setSubType('numeric');
			$maxTemperatureHeat->setUnite('°C');
			$maxTemperatureHeat->setEqLogic_id($eqLogic->getId());
			$maxTemperatureHeat->save();

			$design = $eqLogic->getCmd(null, 'design');
			if (!is_object($design)) {
				$design = new greenmomitCmd();
				$design->setLogicalId('design');
				$design->setIsVisible(0);
				$design->setName(__('Design', __FILE__));
				$design->setTemplate('dashboard', 'tile');
			}
			$design->setType('info');
			$design->setSubType('numeric');
			$design->setEqLogic_id($eqLogic->getId());
			$design->save();

			$state_text = $eqLogic->getCmd(null, 'state_text');
			if (!is_object($state_text)) {
				$state_text= new greenmomitCmd();
				$state_text->setLogicalId('state_text');
				$state_text->setIsVisible(0);
				$state_text->setName(__('Etat Label', __FILE__));
				$state_text->setTemplate('dashboard', 'tile');
			}
			$state_text->setDisplay('generic_type', 'THERMOSTAT_MODE');	
			$state_text->setType('info');
			$state_text->setSubType('string');
			$state_text->setEqLogic_id($eqLogic->getId());
			$state_text->save();


			$controlRelay_text = $eqLogic->getCmd(null, 'controlRelay_text');
			if (!is_object($controlRelay_text)) {
				$controlRelay_text= new greenmomitCmd();
				$controlRelay_text->setLogicalId('controlRelay_text');
				$controlRelay_text->setIsVisible(0);
				$controlRelay_text->setName(__('Relais controlé par text ', __FILE__));
				$controlRelay_text->setTemplate('dashboard', 'tile');
			}
			$controlRelay_text->setType('info');
			$controlRelay_text->setSubType('string');
			$controlRelay_text->setEqLogic_id($eqLogic->getId());
			$controlRelay_text->save();


			$status = $eqLogic->getCmd(null, 'status');
			if (!is_object($status)) {
				$status= new greenmomitCmd();
				$status->setLogicalId('status');
				$status->setIsVisible(0);
				$status->setName(__('Status', __FILE__));
				$status->setTemplate('dashboard', 'tile');
			}
			$status->setDisplay('generic_type', 'THERMOSTAT_STATE_NAME');
			$status->setType('info');
			$status->setSubType('string');
			$status->setEqLogic_id($eqLogic->getId());
			$status->save();
		}
	}

	/*     * *********************Méthodes d'instance************************* */

	public function syncData() {
		$loginApi = self::loginApi();
		$request_http = new com_http(GREENMOMITADDR . '/thermostat/' . $this->getLogicalId() . '?session=' . $loginApi['data']['sessionToken']);
		$result = json_decode(trim($request_http->exec()), true, 512, JSON_BIGINT_AS_STRING);
		if (!isset($result['data'])) {
			log::add('greenmomit', 'debug', __('Aucune donnée trouvée : ', __FILE__) . print_r($result, true));
			return;
		}
		$data = $result['data'];
		log::add('greenmomit', 'debug', print_r($data, true));
		if (!isset($data['record'])) {
			return;
		}
		if (!isset($data['parameters'])) {
			return;
		}
		if (!isset($data['parameters']['temperature'])) {
			$data['parameters']['temperature'] = $data['parameters']['minTemperatureHeat'];		
		}
		if ($data['parameters']['smart'] == '') {
			$data['parameters']['smart'] = 0;
		}
		if ($data['parameters']['presence'] == '') {
			$data['parameters']['presence'] = 0;
		}
		if ($data['parameters']['ambient'] == '') {
			$data['parameters']['ambient'] = 0;
		}
		$this->checkAndUpdateCmd('temperature', $data['record']['temperatureValue']);
		$this->checkAndUpdateCmd('humidity', $data['record']['humidityValue']);
		$this->checkAndUpdateCmd('relay', $data['record']['relays']);
		$this->checkAndUpdateCmd('order', $data['parameters']['temperature']);
		$this->checkAndUpdateCmd('mode', $data['parameters']['useMode']);
		$this->checkAndUpdateCmd('state', $data['parameters']['state']);
		$this->checkAndUpdateCmd('standby', $data['parameters']['standby']);
		$this->checkAndUpdateCmd('controlRelay', $data['parameters']['controlRelay']);
		$this->checkAndUpdateCmd('smart', $data['parameters']['smart']);
		$this->checkAndUpdateCmd('presence', $data['parameters']['presence']);
		$this->checkAndUpdateCmd('ambient', $data['parameters']['ambient']);
		$this->checkAndUpdateCmd('calibration', $data['parameters']['calibration']);
		$this->checkAndUpdateCmd('histeresis', $data['parameters']['histeresis']);
		$this->checkAndUpdateCmd('minTemperatureHeat', $data['parameters']['minTemperatureHeat']);
		$this->checkAndUpdateCmd('maxTemperatureHeat', $data['parameters']['maxTemperatureHeat']);
		$this->checkAndUpdateCmd('design', $data['parameters']['design']);

		if ($data['record']['relays'] == '1') {
			$this->checkAndUpdateCmd('status', 'Actif');
		}
		else {
			$this->checkAndUpdateCmd('status', 'Inactif');
		}

		switch($data['parameters']['state'])  {
  		    case '0':
 			$this->checkAndUpdateCmd('state_text', $this->getCmd('action', 'off')->getName());
			$this->checkAndUpdateCmd('state_off', 1);
			$this->checkAndUpdateCmd('state_manuel', 0);
			$this->checkAndUpdateCmd('state_auto', 0);
  			break;

  		    case '1':
			$this->checkAndUpdateCmd('state_text', $this->getCmd('action', 'manuel')->getName());
			$this->checkAndUpdateCmd('state_off', 0);
			$this->checkAndUpdateCmd('state_manuel', 1);
			$this->checkAndUpdateCmd('state_auto', 0);
  			break;
	
		    case '2':
			$this->checkAndUpdateCmd('state_text', $this->getCmd('action', 'auto')->getName());
			$this->checkAndUpdateCmd('state_off', 0);
			$this->checkAndUpdateCmd('state_manuel', 0);
			$this->checkAndUpdateCmd('state_auto', 1);
  			break;

		    default:
			$this->checkAndUpdateCmd('state_text', 'Inconnu');
			$this->checkAndUpdateCmd('state_off', 0);
			$this->checkAndUpdateCmd('state_manuel', 0);
			$this->checkAndUpdateCmd('state_auto', 0);
  			break;
		}

		switch($data['parameters']['controlRelay']) {
  	    	    case '1':
 			$this->checkAndUpdateCmd('controlRelay_text', 'Antigel');    
  			break;
	
  		    case '2':
 			$this->checkAndUpdateCmd('controlRelay_text', 'Vacances');
  			break;
	
		    case '3':
 			$this->checkAndUpdateCmd('controlRelay_text', 'Manuel');
  			break;
	
		    case '4':
 			$this->checkAndUpdateCmd('controlRelay_text', 'Presence');
  			break;
	
		    case '5':
 			$this->checkAndUpdateCmd('controlRelay_text', 'Ambiance');
  			break;

		    case '6':
 			$this->checkAndUpdateCmd('controlRelay_text', 'Calendrier');
  			break;

		    default: 
			$this->checkAndUpdateCmd('controlRelay_text', 'Indefini');
		}

				
		$_logicalId = 'thermostat';
		if (is_object($_logicalId)) {
			$thermostat= $_logicalId;
		} else {
			$thermostat= $this->getCmd(null, $_logicalId);
		}
		if (is_object($thermostat)) {
			$thermostat->setConfiguration('minValue',  $data['parameters']['minTemperatureHeat']);
			$thermostat->setConfiguration('maxValue',  $data['parameters']['maxTemperatureHeat']);
			$thermostat->save();
			
		}
	
	}


	/*     * **********************Getteur Setteur*************************** */
}

class greenmomitCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */
	public function imperihomeGenerate($ISSStructure) {
		$eqLogic = $this->getEqLogic();
		$object = $eqLogic->getObject();
		$type = 'DevThermostat';
		$info_device = array(
			'id' => $this->getId(),
			'name' => $eqLogic->getName(),
			'room' => (is_object($object)) ? $object->getId() : 99999,
			'type' => $type,
			'params' => array(),
		);
		$parameterlist =  $eqLogic->getCmd('action', 'off')->getName();  				
		$parameterlist =  $parameterlist  .  ','  .  $eqLogic->getCmd('action', 'manuel')->getName(); 		 
		$parameterlist =  $parameterlist  .  ','  .  $eqLogic->getCmd('action', 'auto')->getName();		 
		$info_device['params'] = $ISSStructure[$info_device['type']]['params'];
		$info_device['params'][0]['value'] = '#' . $eqLogic->getCmd('info', 'state_text')->getId() . '#';  	
		$info_device['params'][1]['value'] = '#' . $eqLogic->getCmd('info', 'temperature')->getId() . '#';
		$info_device['params'][2]['value'] = '#' . $eqLogic->getCmd('info', 'order')->getId() . '#';
		$info_device['params'][3]['value'] = 1;
		$info_device['params'][4]['value'] = $parameterlist;   								
		return $info_device;
	}

	public function imperihomeAction($_action, $_value) {
		$eqLogic = $this->getEqLogic();
		if ($_action == 'setSetPoint') {
			$cmd = $eqLogic->getCmd('action', 'thermostat');
			if (is_object($cmd)) {
				$cmd->execCmd(array('slider' => $_value));
			}
		}
		if ($_action == 'setMode') {
			if ($_value == $eqLogic->getCmd('action', 'off')->getName()) {
				$cmd = $eqLogic->getCmd('action', 'off');
			}
			if ($_value == $eqLogic->getCmd('action', 'manuel')->getName()) {
				$cmd = $eqLogic->getCmd('action', 'manuel');
			}
			if ($_value == $eqLogic->getCmd('action', 'auto')->getName()) {
				$cmd = $eqLogic->getCmd('action', 'auto');
			}
			if (is_object($cmd)) {
				$cmd->execCmd(array());
			}
		}
	}

	public function imperihomeCmd() {
		if ($this->getLogicalId() == 'order') {
			return true;
		}
		return false;
	}

	public function execute($_options = array()) {
		if ($this->getType() == 'info') {
			return '';
		}
		$loginApi = greenmomit::loginApi();
		$eqLogic = $this->getEqLogic();
		$url = GREENMOMITADDR . '/thermostat/' . $eqLogic->getLogicalId() . '?session=' . $loginApi['data']['sessionToken'];

		if ($this->getLogicalId() == 'thermostat') {
			$request_http = new com_http($url . '&values=temperature&temperature=' . $_options['slider']);
			$request_http->setPut(
				array(
					'values' => 'temperature',
					'temperature' => $_options['slider'],
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'smart_on') {
			$request_http = new com_http($url . '&values=advancedSettings&smart=on');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'smart' => 'on',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'smart_off') {
			$request_http = new com_http($url . '&values=advancedSettings&smart=off');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'smart' => 'off',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'presence_on') {
			$request_http = new com_http($url . '&values=advancedSettings&presence=on');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'presence' => 'on',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'presence_off') {
			$request_http = new com_http($url . '&values=advancedSettings&presence=off');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'presence' => 'off',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'ambient_on') {
			$request_http = new com_http($url . '&values=advancedSettings&ambient=on');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'ambient' => 'on',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'ambient_off') {
			$request_http = new com_http($url . '&values=advancedSettings&ambient=off');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'ambient' => 'off',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'standby_on') {
			$request_http = new com_http($url . '&values=advancedSettings&standby=on');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'standby' => 'on',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'standby_off') {
			$request_http = new com_http($url . '&values=advancedSettings&standby=off');
			$request_http->setPut(
				array(
					'values' => 'advancedSettings',
					'standby' => 'off',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'off') {
			$request_http = new com_http($url . '&values=state&state=0');
			$request_http->setPut(
				array(
					'values' => 'state',
					'state' => '0',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'manuel') {
			$request_http = new com_http($url . '&values=state&state=1');
			$request_http->setPut(
				array(
					'values' => 'state',
					'state' => '1',
				)
			);
			$request_http->exec(4, 2);
		}

		if ($this->getLogicalId() == 'auto') {
			$request_http = new com_http($url . '&values=state&state=2');
			$request_http->setPut(
				array(
					'values' => 'state',
					'state' => '2',
				)
			);
			$request_http->exec(4, 2);
		}
		sleep(1);
		$eqLogic->syncData();
	}

	/*     * **********************Getteur Setteur*************************** */
}

?>
