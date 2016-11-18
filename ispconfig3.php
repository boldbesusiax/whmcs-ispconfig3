<?php
/*
 * 
 *  ISPConfig v3.x module for WHMCS v5.x or Higher
 *  Copyright (C) 2014, 2015  Shane Chrisp
 *
 *  Updated for ISPConfig 3.1 and WHMCS v7 by Dylan Pawlak
 * 
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

ini_set('error_reporting', E_ALL & ~E_NOTICE);
ini_set("display_errors", 0); // Set this option to Zero on a production machine.
openlog( "ispconfig3", LOG_PID | LOG_PERROR, LOG_LOCAL0 );

function ispconfig3_MetaData() {
	return array(
		'DisplayName' => 'ISPConfig 3.1',
		'APIVersion' => '1.0'
		);	
}

function ispconfig3_ConfigOptions() {
	$config = array(
		'ISPConfig Remote User' => array(
			'Type' => 'text',
			'Size' => '32',
			'Description' => 'Remote user in ISPConfig (System->User Management->Remote Users)'
		),
		'ISPConfig Remote Password' => array(
			'Type' => 'text',
			'Size' => '32',
			'Description' => 'Password for Remote User in ISPConfig'
		),
		'ISPConfig URL' => array(
			'Type' => 'text',
			'Size' => '32',
			'Description' => 'Example: ispconfig.example.com:8080 (Do not include http/https)'
		),
		'Use SSL to connect to ISPConfig?' => array(
			'Type' => 'yesno'
		),
		'ISPConfig Template ID' => array(
			'Type' => 'text',
			'Size' => '3',
			'Description' => 'The template ID number as it appears in ISPConfig'
		),
		'Shell Access?' => array(
			'Type' => 'dropdown',
			'Options' => 'no,jailkit'
		),
		'Create Website Automatically?' => array(
			'Type' => 'yesno'
		),
		'PHP Mode' => array(
			'Type' => 'dropdown',
			'Options' => 'no,cgi,fast-cgi,php-fpm,hhvm'
		),
		'Website Storage Quota' => array(
			'Type' => 'text',
			'Size' => '8',
			'Description' => 'MB'
		),
		'Website Traffic Quota' => array(
			'Type' => 'text',
			'Size' => '8',
			'Description' => 'MB'
		),
		'Website Setting: Enable CGI?' => array(
			'Type' => 'yesno'
		),
		'Website Setting: Enable SSI?' => array(
			'Type' => 'yesno'
		),
		'Website Setting: Enable Ruby?' => array(
			'Type' => 'yesno'
		),
		'Website Setting: Force SuExec?' => array(
			'Type' => 'yesno'
		),
		'Create Auto-Subdomain?' => array(
			'Type' => 'dropdown',
			'Options' => 'none,www,*'
		),
		'Create FTP Account?' => array(
			'Type' => 'yesno'
		),
		'Create DNS Zone?' => array(
			'Type' => 'yesno'
		),
		'DNS Template ID' => array(
			'Type' => 'text',
			'Size' => '3'
		),
		'Create Mail Domain?' => array(
			'Type' => 'yesno'
		),
		'Enable account on creation?' => array(
			'Type' => 'yesno'
		),
		
	);
	
	return $config;
}

function ispconfig3_CreateAccount($params) {
	$soapuser = $params['configoption1'];
	$soappass = $params['configoption2'];
	$soapurl = $params['configoption3'];
	$usessl = $params['configoption4'];
	
	$accountid = $params['accountid'];
	$productid = $params['pid'];
	$domain = strtolower($params['domain']);
	$clientdetails = $params['clientsdetails'];
	
	$fullname = htmlspecialchars_decode($clientdetails['firstname']) . ' ' . htmlspecialchars_decode($clientdetails['lastname']);
	$companyname = htmlspecialchars_decode($clientdetails['companyname']);
	$address = $clientdetails['address1'];
	if (!empty($clientdetails['address2'])) {
		$address = $clientdetails['address1'] . $clientdetails['address2'];
	}
	$zip = $clientdetails['postcode'];
	$city = $clientdetails['city'];
	$state = $clientdetails['state'];
	$country = $clientdetails['country'];
	$email = $clientdetails['email'];
	$phonenumber = $clientdetails['phonenumberformatted'];
	
	$username = $params['username'];
	$password = $params['password'];
	$templateid = $params['configoption5'];
	$shellaccess = $params['configoption6'];
	$createweb = $params['configoption7'];
	$phpmode = $params['configoption8'];
	$webstorage = $params['configoption9'];
	$webtraffic = $params['configoption10'];
	$params['configoption11'] == 'on' ? $webenablecgi = 'y' : $webenablecgi = '';
	$params['configoption12'] == 'on' ? $webenablessi = 'y' : $webenablessi = '';
	$params['configoption13'] == 'on' ? $webenableruby = 'y' : $webenableruby = '';
	$params['configoption14'] == 'on' ? $webenablesuexec = 'y' : $webenablesuexec = '';
	$webautosubdomain = $params['configoption15'];
	$webcreateftp = $params['configoption16'];
	$createdns = $params['configoption17'];
	$dnstemplateid = $params['configoption18'];
	$createmail = $params['configoption19'];
	
	$active = $params['configoption20'];
	
	logModuleCall('ispconfig3','CreateClient',$params['clientdetails'],$params,'','');
	
	if($usessl == 'on') {
		
		$soap_url = 'https://'.$soapurl.'/remote/index.php';
		$soap_uri = 'https://'.$soapurl.'/remote/';
	
	}
	else {
		
		$soap_url = 'http://'.$soapurl.'/remote/index.php';
		$soap_uri = 'http://'.$soapurl.'/remote/';
	}
	
	if((isset($username) && $username != '') && (isset($password) && $password != '')) {
		try {
			$soap_client = new SoapClient(
				null,
				array(
					'location' => $soap_url,
					'uri' => $soap_uri,
					'exceptions' => 1,
					'stream_context' => stream_context_create(
						array(
							'ssl' => array(
								'verify_peer' => false,
								'verify_peer_name' => false)
							)
						),
					'trace' => false
				)
			);
			
			$soap_session_id = $soap_client->login($soapuser,$soappass);
			
			if(isset($params['serverip'])) {
				$nameserver = 0;
				$nameserverslave = 0;
				$defaultwebserver = 0;
				$defaultfileserver = 0;
				$defaultdbserver = 0;
				$defaultmailserver = 0;
				$defaultdnsserver = 0;
				$index = 0;
				$server = array();
				$servermaster = array();
				
				$soap_result = $soap_client->server_get_serverid_by_ip($soap_session_id,$params['serverip']);
				$serverservices = $soap_client->server_get_functions($soap_session_id,$soap_result[0]['server_id']);
				
				//By default: try to associate HTTP, FTP and Database services to same (web)server
				if($serverservices[0]['web_server'] == 1) {
					$defaultwebserver = $soap_result[0]['server_id'];
					if($serverservices[0]['file_server'] == 1) {
						$defaultfileserver = $soap_result[0]['server_id'];
					}
					if($serverservices[0]['db_server'] == 1) {
						$defaultdbserver = $soap_result[0]['server_id'];
					}
				}
				
				$soap_result = $soap_client->server_get_all($soap_session_id);
				$mail = 0;
				$mastermail = 0;
				$dns = 0;
				$masterdns = 0;
				$web = 0;
				$masterweb = 0;
				$file = 0;
				$masterfile = 0;
				$db = 0;
				$masterdb = 0;
				
				while($index <= count($soap_result)) {
					$serverservices = $soap_client->server_get_functions($soap_session_id,$soap_result[$index]['server_id']);
					
					if($serverservices[0]['mail_server'] == 1) {
						$server['mail_server'][$mail]['server_id'] = $soap_result[$index]['server_id'];
						$server['mail_server'][$mail]['server_name'] = $soap_result[$index]['server_name'];
						if($serverservices[0]['mirror_server_id'] == 0) {
							$servermaster['mail_server'][$mastermail]['server_id'] = $soap_result[$index]['server_id'];
							$servermaster['mail_server'][$mastermail]['server_name'] = $soap_result[$index]['server_name'];
							++$mastermail;
						}
						++$mail;
					}
					if($serverservices[0]['dns_server'] == 1) {
						$server['dns_server'][$dns]['server_id'] = $soap_result[$index]['server_id'];
						$server['dns_server'][$dns]['server_name'] = $soap_result[$index]['server_name'];
						if($serverservices[0]['mirror_server_id'] == 0) {
							$servermaster['dns_server'][$masterdns]['server_id'] = $soap_result[$index]['server_id'];
							$servermaster['dns_server'][$masterdns]['server_name'] = $soap_result[$index]['server_name'];
							++$masterdns;
						}
						++$dns;
					}
					if($serverservices[0]['web_server'] == 1) {
						$server['web_server'][$web]['server_id'] = $soap_result[$index]['server_id'];
						$server['web_server'][$web]['server_name'] = $soap_result[$index]['server_name'];
						if($serverservices[0]['mirror_server_id'] == 0) {
							$servermaster['web_server'][$masterweb]['server_id'] = $soap_result[$index]['server_id'];
							$servermaster['web_server'][$masterweb]['server_name'] = $soap_result[$index]['server_name'];
							++$masterweb;
						}
						++$web;
					}
					if($serverservices[0]['file_server'] == 1) {
						$server['file_server'][$file]['server_id'] = $soap_result[$index]['server_id'];
						$server['file_server'][$file]['server_name'] = $soap_result[$index]['server_name'];
						if($serverservices[0]['mirror_server_id'] == 0) {
							$servermaster['file_server'][$masterfile]['server_id'] = $soap_result[$index]['server_id'];
							$servermaster['file_server'][$masterfile]['server_name'] = $soap_result[$index]['server_name'];
							++$masterfile;
						}
						++$file;
					}
					if($serverservices[0]['db_server'] == 1) {
						$server['db_server'][$db]['server_id'] = $soap_result[$index]['server_id'];
						$server['db_server'][$db]['server_name'] = $soap_result[$index]['server_name'];
						if($serverservices[0]['mirror_server_id'] == 0) {
							$servermaster['db_server'][$masterdb]['server_id'] = $soap_result[$index]['server_id'];
							$servermaster['db_server'][$masterdb]['server_name'] = $soap_result[$index]['server_name'];
							++$masterdb;
						}
						++$db;
					}
					++$index;
				}
				//need better logic: if a use case includes file or db servers but no web servers or vice versa
				if($defaultwebserver == 0) { //some people might have zero servers for some reason, validate that a setup with 0 servers will return 0 for that server field
					if(count($servermaster['web_server']) == 1) {
						$defaultwebserver = $servermaster['web_server'][0]['server_id'];
					}
					else {
						$rand = rand(0,(count($servermaster['web_server']) - 1));
						$defaultwebserver = $servermaster['web_server'][$rand]['server_id'];
					}
					
					if(count($servermaster['file_server']) == 1) {
						$defaultfileserver = $servermaster['file_server'][0]['server_id'];
					}
					else {
						$rand = rand(0,(count($servermaster['file_server']) - 1));
						$defaultfileserver = $servermaster['file_server'][$rand]['server_id'];
					}
					
					if(count($servermaster['db_server']) == 1) {
						$defaultdbserver = $servermaster['db_server'][0]['server_id'];
					}
					else {
						$rand = rand(0,(count($servermaster['db_server']) - 1));
						$defaultdbserver = $servermaster['db_server'][$rand]['server_id'];
					}
				}
				
				if(count($servermaster['mail_server']) == 1) {
					$defaultmailserver = $servermaster['mail_server'][0]['server_id'];
					$defaultmailservername = $servermaster['mail_server'][0]['server_name'];
				}
				else { //do the same thing with dns servers, select master based off mirror id and avoid that looping nightmare
					$rand = rand(0,(count($servermaster['mail_server']) - 1));
					$defaultmailserver = $servermaster['mail_server'][$rand]['server_id'];
					$defaultmailservername = $servermaster['mail_server'][$rand]['server_name'];
				}
					
				if(count($servermaster['dns_server']) == 1) {
					$defaultdnsserver = $server['dns_server'][0]['server_id'];
					$nameserver = $server['dns_server'][0]['server_name'];
				}
				else {
					$rand = rand(0,(count($servermaster['dns_server']) -1));
					$defaultdnsserver = $servermaster['dns_server'][$rand]['server_id'];
					$nameserver = $servermaster['dns_server'][$rand]['server_name'];
				}
				
				$index = 0;
				$nameserverslave = 0;
				
				while($index <= count($server['dns_server'])) {
					$mirror_id = $soap_client->server_get_functions($soap_session_id,$server['dns_server'][$index]['server_id']);
					if($mirror_id[0]['mirror_server_id'] == $defaultdnsserver) {
						$nameserverslave = $server['dns_server'][$index]['server_name'];
					}
					++$index;
				}
				
				logModuleCall('ispconfig3','DNS Mirror ID',$nameserverslave,$defaultdnsserver.' - '.$nameserver.' - '.$mirror_id[0]['mirror_server_id'],'','');
				logModuleCall('ispconfig3','ServerList',$servermaster,$server,'','');
				
				$ispconfigparams = array(
					'company_name' => $companyname,
					'contact_name' => $fullname,
					'customer_no' => $accountid,
					'username' => $username,
					'password' => $password,
					'language' => 'en',
					'usertheme' => 'default',
					'street' => $address,
					'zip' => $zip,
					'city' => $city,
					'state' => $state,
					'country' => $country,
					'telephone' => $phonenumber,
					'mobile' => '',
					'fax' => '',
					'email' => $email,
					'template_master' => $templateid,
					'web_php_options' => 'no,fast-cgi,cgi,php-fpm,hhvm',
					'ssh_chroot' => $shellaccess,
					'default_mailserver' => $defaultmailserver,
					'default_webserver' => $defaultwebserver,
					'default_dbserver' => $defaultdbserver,
					'default_dnsserver' => $defaultdnsserver,
					'locked' => '0',
					'created_at' => date('Y-m-d')
					);
				
				$reseller_id = 0;
				$client_id = $soap_client->client_add($soap_session_id,$reseller_id,$ispconfigparams);
				logModuleCall('ispconfig3','CreateClient',$client_id,$ispconfigparams,'','');
				
				if($createdns == 'on') { //figure out how the ispconfig serial is generated (hint date) + stamp records w/ serial
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'origin' => $domain.'.',
						'ns' => $nameserver.'.',
						'mbox' => 'webmaster.'.$domain.'.',
						'refresh' => '7200',
						'retry' => '540',
						'expire' => '604800',
						'minimum' => '3600',
						'ttl' => '3600',
						'active' => 'y'
					);
					$dns_id = $soap_client->dns_zone_add($soap_session_id,$client_id,$ispconfigparams);
					
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => $domain.'.',
						'type' => 'A',
						'data' => $params['serverip'],
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_a_add($soap_session_id,$client_id,$ispconfigparams);
					
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => 'www',
						'type' => 'A',
						'data' => $params['serverip'],
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_a_add($soap_session_id,$client_id,$ispconfigparams);
					
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => 'mail',
						'type' => 'A',
						'data' => $params['serverip'],
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_a_add($soap_session_id,$client_id,$ispconfigparams);
					
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => $domain.'.',
						'type' => 'MX',
						'data' => $defaultmailservername.'.',
						'aux' => '10',
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_mx_add($soap_session_id,$client_id,$ispconfigparams);
					
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => $domain.'.',
						'type' => 'NS',
						'data' => $nameserver.'.',
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_ns_add($soap_session_id,$client_id,$ispconfigparams);
					
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => $domain.'.',
						'type' => 'NS',
						'data' => $nameserverslave.'.',
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_ns_add($soap_session_id,$client_id,$ispconfigparams);
				
					$ispconfigparams = array(
						'server_id' => $defaultdnsserver,
						'zone' => $dns_id,
						'name' => $domain.'.',
						'type' => 'TXT',
						'data' => 'v=spf1 mx a ~all',
						'ttl' => '3600',
						'active' => 'y'
					);
					$zone_id = $soap_client->dns_txt_add($soap_session_id,$client_id,$ispconfigparams);
						
					//$dns_id = $soap_client->dns_templatezone_add($soap_session_id,$client_id,$dnstemplateid,$domain,$params['serverip'],$nameserver,$nameserverslave,'webmaster@' . $domain);
					logModuleCall('ispconfig3','CreateDNSZone',$domain,'DNS Template: '.$dnstemplateid,'','');
					logModuleCall('ispconfig3','CreateDNSZone',$domain,'IP: '.$params['serverip'].' NS1: '.$nameserver.' NS2: '.$nameserverslave.' Email: '.'webmaster@'.$domain,'','');
				}
				
				if($createmail == 'on') {
					$ispconfigparams = array(
						'server_id' => $defaultmailserver,
						'domain' => $domain,
						'active' => 'y'
						);
						
					$mail_id = $soap_client->mail_domain_add($soap_session_id,$client_id,$ispconfigparams);
					logModuleCall('ispconfig3','CreateMailDomain',$mail_id,$ispconfigparams,'','');
				}
				
				if($createweb == 'on') {
					$ispconfigparams = array(
						'server_id' => $defaultwebserver,
						'ip_address' => '*',
						'domain' => $domain,
						'type' => 'vhost',
						'parent_domain_id' => '0',
						'vhost_type' => 'name',
						'hd_quota' => $webstorage,
						'traffic_quota' => $webtraffic,
						'cgi' => $webenablecgi,
						'ssi' => $webenablessi,
						'ruby' => $webenableruby,
						'suexec' => $webenablesuexec,
						'errordocs' => '',
						'is_subdomainwww' => 1,
						'subdomain' => $webautosubdomain,
						'php' => $phpmode,
						'redirect_type' => '',
						'redirect_path' => '',
						'ssl' => 'y',
						'ssl_letsencrypt' => 'n',
						'ssl_state' => $state,
						'ssl_locality' => $city,
						'ssl_organisation' => $companyname,
						'ssl_organisation_unit' => 'IT',
						'ssl_country' => $country,
						'ssl_domain' => $domain,
						'ssl_request' => '',
						'ssl_key' => '',
						'ssl_cert' => '',
						'ssl_bundle' => '',
						'ssl_action' => 'create',
						'stats_password' => $password,
						'stats_type' => 'webalizer',
						'allow_override' => 'All',
						'php_open_basedir' => '/',
						'php_fpm_use_socket' => 'y',
						'pm' => 'ondemand',
						'pm_max_children' => '10',
						'pm_start_servers' => '2',
						'pm_min_spare_servers' => '1',
						'pm_max_spare_servers' => '5',
						'pm_process_idle_timeout' => '10',
						'pm_max_requests' => '1024',
						'custom_php_ini' => '',
						'backup_interval' => 'daily',
						'backup_copies' => 3,
						'active' => 'y',
						'traffic_quota_lock' => 'n',
						'http_port' => '80',
						'https_port' => '443',
						'added_date' => date("Y-m-d"),
						'added_by' => $soapuser
					);
					
					$website_id = $soap_client->sites_web_domain_add($soap_session_id,$client_id,$ispconfigparams,false);
					logModuleCall('ispconfig3','CreateWebDomain',$website_id,$ispconfigparams,'','');
					
					if($webcreateftp == 'on') {
						$domain_info = $soap_client->sites_web_domain_get($soap_session_id,$website_id);
						$ispconfigparams = array(
							'server_id' => $defaultwebserver,
							'parent_domain_id' => $website_id,
							'username' => $username,
							'password' => $password,
							'quota_size' => -1,
							'active' => 'y',
							'uid' => $domain_info['system_user'],
							'gid' => $domain_info['system_group'],
							'dir' => $domain_info['document_root'],
							'quota_files' => -1,
							'ul_ratio' => -1,
							'dl_ratio' => -1,
							'ul_bandwidth' => -1,
							'dl_bandwidth' => -1
						);
						
						$ftp_id = $soap_client->sites_ftp_user_add($soap_session_id,$client_id,$ispconfigparams);
						logModuleCall('ispconfig3','CreateFtpUser',$ftp_id,$ispconfigparams,'','');
					}
				}
			}
			$soap_client->logout($soap_session_id);
			$successful = 1;
		}
		catch(SoapFault $e) {
			$error = 'SOAP Error: ' . $e->getMessage();
			$successful = 0;
			logModuleCall('ispconfig3','Create Failed',$e->getMessage(),$params,'','');
		}
		
		if($successful == 1) {
			$result = 'success';
		}
		else {
			$result = $error;
		}
	}
	else {
		$result = 'Username or Password is blank or not set!';
	}
	
	return $result;
}

function ispconfig3_TerminateAccount($params) {
	$soapuser = $params['configoption1'];
	$soappass = $params['configoption2'];
	$soapurl = $params['configoption3'];
	$usessl = $params['configoption4'];
	
	$accountid = $params['accountid'];
	$productid = $params['pid'];
	$domain = strtolower($params['domain']);
	$clientdetails = $params['clientsdetails'];
	
	$fullname = htmlspecialchars_decode($clientdetails['firstname']) . ' ' . htmlspecialchars_decode($clientdetails['lastname']);
	$companyname = htmlspecialchars_decode($clientdetails['companyname']);
	$address = $clientdetails['address1'];
	if (!empty($clientdetails['address2'])) {
		$address = $clientdetails['address1'] . $clientdetails['address2'];
	}
	$zip = $clientdetails['postcode'];
	$city = $clientdetails['city'];
	$state = $clientdetails['state'];
	$country = $clientdetails['country'];
	$email = $clientdetails['email'];
	$phonenumber = $clientdetails['phonenumberformatted'];
	
	$username = $params['username'];
	$password = $params['password'];
	$templateid = $params['configoption5'];
	$shellaccess = $params['configoption6'];
	$createweb = $params['configoption7'];
	$phpmode = $params['configoption8'];
	$webstorage = $params['configoption9'];
	$webtraffic = $params['configoption10'];
	$params['configoption11'] == 'on' ? $webenablecgi = 'y' : $webenablecgi = '';
	$params['configoption12'] == 'on' ? $webenablessi = 'y' : $webenablessi = '';
	$params['configoption13'] == 'on' ? $webenableruby = 'y' : $webenableruby = '';
	$params['configoption14'] == 'on' ? $webenablesuexec = 'y' : $webenablesuexec = '';
	$webautosubdomain = $params['configoption15'];
	$webcreateftp = $params['configoptions16'];
	$createdns = $params['configoption17'];
	$dnstemplateid = $params['configoption18'];
	$createmail = $params['configoption19'];
	
	$active = $params['configoption20'];
	
	logModuleCall('ispconfig3','CreateClient',$params['clientdetails'],$params,'','');
	
	if ($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl . '/remote/index.php';
		$soap_uri = 'https://' . $soapurl . '/remote/';
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl . '/remote/index.php';
		$soap_uri = 'http://' . $soapurl . '/remote/';
	}
	
	if ((isset($username) && $username != '') && (isset($password) && $password != '')) {
		try {
			
			$soap_client = new SoapClient(
				null,
				array( 'location' => $soap_url,
					'uri' => $soap_uri,
					'exceptions' => 1,
					'stream_context' => stream_context_create(
						array('ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false)
							)
						),
					'trace' => false
				)
			);
			/* Authenticate and get serverID's from WHMCS */
			$soap_session_id = $soap_client->login($soapuser,$soappass);
			
			$client_id = $soap_client->client_get_by_username($soap_session_id,$username);
			$result = $soap_client->client_delete_everything($soap_session_id,$client_id['client_id']);
			logModuleCall('ispconfig3','Terminate Account',$client_id['client_id'],$ispconfigparams,'','');
			$soap_client->logout($soap_session_id);
			
			if($result == 1) {
				$successful = '1';
			}
			else {
				$successful = '0';
				$result = 'Terminate client failed';
			}
		}
		catch (SoapFault $e) {
			$error = 'SOAP Error: ' . $e->getMessage();
			$successful = '0';
		}
		
		if($successful == 1) {
			$result = 'success';
		}
		else {
			$result = 'Error: ' . $error;
		}
	}
	else {
		$result = 'Username or Password is blank or not set';
	}
	
	return $result;
}

function ispconfig3_ChangePackage($params) {
	$soapuser = $params['configoption1'];
	$soappass = $params['configoption2'];
	$soapurl = $params['configoption3'];
	$usessl = $params['configoption4'];
	
	$accountid = $params['accountid'];
	$productid = $params['pid'];
	$domain = strtolower($params['domain']);
	$clientdetails = $params['clientsdetails'];
	
	$fullname = htmlspecialchars_decode($clientdetails['firstname']) . ' ' . htmlspecialchars_decode($clientdetails['lastname']);
	$companyname = htmlspecialchars_decode($clientdetails['companyname']);
	$address = $clientdetails['address1'];
	if (!empty($clientdetails['address2'])) {
		$address = $clientdetails['address1'] . $clientdetails['address2'];
	}
	$zip = $clientdetails['postcode'];
	$city = $clientdetails['city'];
	$state = $clientdetails['state'];
	$country = $clientdetails['country'];
	$email = $clientdetails['email'];
	$phonenumber = $clientdetails['phonenumberformatted'];
	
	$username = $params['username'];
	$password = $params['password'];
	$templateid = $params['configoption5'];
	$shellaccess = $params['configoption6'];
	$createweb = $params['configoption7'];
	$phpmode = $params['configoption8'];
	$webstorage = $params['configoption9'];
	$webtraffic = $params['configoption10'];
	$params['configoption11'] == 'on' ? $webenablecgi = 'y' : $webenablecgi = '';
	$params['configoption12'] == 'on' ? $webenablessi = 'y' : $webenablessi = '';
	$params['configoption13'] == 'on' ? $webenableruby = 'y' : $webenableruby = '';
	$params['configoption14'] == 'on' ? $webenablesuexec = 'y' : $webenablesuexec = '';
	$webautosubdomain = $params['configoption15'];
	$webcreateftp = $params['configoptions16'];
	$createdns = $params['configoption17'];
	$dnstemplateid = $params['configoption18'];
	$createmail = $params['configoption19'];
	
	$active = $params['configoption20'];
	
	logModuleCall('ispconfig3','CreateClient',$params['clientdetails'],$params,'','');
	
	if ($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl . '/remote/index.php';
		$soap_uri = 'https://' . $soapurl . '/remote/';
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl . '/remote/index.php';
		$soap_uri = 'http://' . $soapurl . '/remote/';
	}
	
	if ((isset($username) && $username != '') && (isset($password) && $password != '')) {
		try {
			
			$soap_client = new SoapClient(
				null,
				array( 'location' => $soap_url,
					'uri' => $soap_uri,
					'exceptions' => 1,
					'stream_context' => stream_context_create(
						array('ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false)
							)
					),
					'trace' => false
				)
			);
			/* Authenticate and get serverID's from WHMCS */
			$soap_session_id = $soap_client->login($soapuser,$soappass);
			$ispconfigparams = array(
				'template_master' => $templateid
			);
			$reseller_id = 0;
			$client_id = $soap_client->client_get_by_username($soap_session_id,$username);
			$result = $soap_client->client_update($soap_session_id,$client_id['client_id'],$reseller_id,$ispconfigparams);
			logModuleCall('ispconfig3','Change Package',$client_id['client_id'],$ispconfigparams,'','');
			$soap_client->logout($soap_session_id);
			
			if($result == 1) {
				$successful = '1';
			}
			else {
				$successful = '0';
				$result = 'Change package failed';
			}
		}
		catch (SoapFault $e) {
			$error = 'SOAP Error: ' . $e->getMessage();
			$successful = '0';
		}
		
		if($successful == 1) {
			$result = 'success';
		}
		else {
			$result = 'Error: ' . $error;
		}
	}
	else {
		$result = 'Username or Password is blank or not set';
	}
	
	return $result;
}

function ispconfig3_SuspendAccount($params) {
	$soapuser = $params['configoption1'];
	$soappass = $params['configoption2'];
	$soapurl = $params['configoption3'];
	$usessl = $params['configoption4'];
	
	$accountid = $params['accountid'];
	$productid = $params['pid'];
	$domain = strtolower($params['domain']);
	$clientdetails = $params['clientsdetails'];
	
	$fullname = htmlspecialchars_decode($clientdetails['firstname']).' '.htmlspecialchars_decode($clientdetails['lastname']);
	$companyname = htmlspecialchars_decode($clientdetails['companyname']);
	$address = $clientdetails['address1'];
	if(!empty($clientdetails['address2'])) {
		$address = $clientdetails['address1'].$clientdetails['address2'];
	}
	$zip = $clientdetails['postcode'];
	$city = $clientdetails['city'];
	$state = $clientdetails['state'];
	$country = $clientdetails['country'];
	$email = $clientdetails['email'];
	$phonenumber = $clientdetails['phonenumberformatted'];
	
	$username = $params['username'];
	$password = $params['password'];
	$templateid = $params['configoption5'];
	$shellaccess = $params['configoption6'];
	$createweb = $params['configoption7'];
	$phpmode = $params['configoption8'];
	$webstorage = $params['configoption9'];
	$webtraffic = $params['configoption10'];
	$params['configoption11'] == 'on' ? $webenablecgi = 'y' : $webenablecgi = '';
	$params['configoption12'] == 'on' ? $webenablessi = 'y' : $webenablessi = '';
	$params['configoption13'] == 'on' ? $webenableruby = 'y' : $webenableruby = '';
	$params['configoption14'] == 'on' ? $webenablesuexec = 'y' : $webenablesuexec = '';
	$webautosubdomain = $params['configoption15'];
	$webcreateftp = $params['configoptions16'];
	$createdns = $params['configoption17'];
	$dnstemplateid = $params['configoption18'];
	$createmail = $params['configoption19'];
	
	$active = $params['configoption20'];
	
	logModuleCall('ispconfig3','CreateClient',$params['clientdetails'],$params,'','');
	
	if($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl . '/remote/index.php';
		$soap_uri = 'https://' . $soapurl . '/remote/';
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl . '/remote/index.php';
		$soap_uri = 'http://' . $soapurl . '/remote/';
	}
	
	if ((isset($username) && $username != '') && (isset($password) && $password != '')) {
		try {
			
			$soap_client = new SoapClient(
				null,
				array( 'location' => $soap_url,
					'uri' => $soap_uri,
					'exceptions' => 1,
					'stream_context' => stream_context_create(
						array('ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false)
							)
					),
					'trace' => false
				)
			);
			/* Authenticate and get serverID's from WHMCS */
			$soap_session_id = $soap_client->login($soapuser,$soappass);
			$ispconfigparams = array(
				'locked' => 'y'
			);
			$reseller_id = 0;
			$client_id = $soap_client->client_get_by_username($soap_session_id,$username);
			$result = $soap_client->client_update($soap_session_id,$client_id['client_id'],$reseller_id,$ispconfigparams);
			logModuleCall('ispconfig3','Suspend Account',$client_id['client_id'],$ispconfigparams,'','');
			$soap_client->logout($soap_session_id);
			
			if($result == 1) {
				$successful = '1';
			}
			else {
				$successful = '0';
				$result = 'Suspend client failed';
			}
		}
		catch (SoapFault $e) {
			$error = 'SOAP Error: ' . $e->getMessage();
			$successful = '0';
		}
		
		if($successful == 1) {
			$result = 'success';
		}
		else {
			$result = 'Error: ' . $error;
		}
	}
	else {
		$result = 'Username or Password is blank or not set';
	}
	
	return $result;
}

function ispconfig3_UnsuspendAccount($params) {
	$soapuser = $params['configoption1'];
	$soappass = $params['configoption2'];
	$soapurl = $params['configoption3'];
	$usessl = $params['configoption4'];
	
	$accountid = $params['accountid'];
	$productid = $params['pid'];
	$domain = strtolower($params['domain']);
	$clientdetails = $params['clientsdetails'];
	
	$fullname = htmlspecialchars_decode($clientdetails['firstname']) . ' ' . htmlspecialchars_decode($clientdetails['lastname']);
	$companyname = htmlspecialchars_decode($clientdetails['companyname']);
	$address = $clientdetails['address1'];
	if(!empty($clientdetails['address2'])) {
		$address = $clientdetails['address1'] . $clientdetails['address2'];
	}
	$zip = $clientdetails['postcode'];
	$city = $clientdetails['city'];
	$state = $clientdetails['state'];
	$country = $clientdetails['country'];
	$email = $clientdetails['email'];
	$phonenumber = $clientdetails['phonenumberformatted'];
	
	$username = $params['username'];
	$password = $params['password'];
	$templateid = $params['configoption5'];
	$shellaccess = $params['configoption6'];
	$createweb = $params['configoption7'];
	$phpmode = $params['configoption8'];
	$webstorage = $params['configoption9'];
	$webtraffic = $params['configoption10'];
	$params['configoption11'] == 'on' ? $webenablecgi = 'y' : $webenablecgi = '';
	$params['configoption12'] == 'on' ? $webenablessi = 'y' : $webenablessi = '';
	$params['configoption13'] == 'on' ? $webenableruby = 'y' : $webenableruby = '';
	$params['configoption14'] == 'on' ? $webenablesuexec = 'y' : $webenablesuexec = '';
	$webautosubdomain = $params['configoption15'];
	$webcreateftp = $params['configoptions16'];
	$createdns = $params['configoption17'];
	$dnstemplateid = $params['configoption18'];
	$createmail = $params['configoption19'];
	
	$active = $params['configoption20'];
	
	logModuleCall('ispconfig3','CreateClient',$params['clientdetails'],$params,'','');
	
	if ($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl . '/remote/index.php';
		$soap_uri = 'https://' . $soapurl . '/remote/';
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl . '/remote/index.php';
		$soap_uri = 'http://' . $soapurl . '/remote/';
	}
	
	if ((isset($username) && $username != '') && (isset($password) && $password != '')) {
		try {
			
			$soap_client = new SoapClient(
				null,
				array( 'location' => $soap_url,
					'uri' => $soap_uri,
					'exceptions' => 1,
					'stream_context' => stream_context_create(
						array('ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false)
							)
					),
					'trace' => false
				)
			);
			/* Authenticate and get serverID's from WHMCS */
			$soap_session_id = $soap_client->login($soapuser,$soappass);
			$ispconfigparams = array(
				'locked' => 'n'
			);
			$reseller_id = 0;
			$client_id = $soap_client->client_get_by_username($soap_session_id,$username);
			$result = $soap_client->client_update($soap_session_id,$client_id['client_id'],$reseller_id,$ispconfigparams);
			logModuleCall('ispconfig3','Unsuspend Account',$client_id['client_id'],$ispconfigparams,'','');
			$soap_client->logout($soap_session_id);
			
			if($result == 1) {
				$successful = '1';
			}
			else {
				$successful = '0';
				$result = 'Unsuspend client failed';
			}
		}
		catch (SoapFault $e) {
			$error = 'SOAP Error: ' . $e->getMessage();
			$successful = '0';
		}
		
		if($successful == 1) {
			$result = 'success';
		}
		else {
			$result = 'Error: ' . $error;
		}
	}
	else {
		$result = 'Username or Password is blank or not set';
	}
	
	return $result;
}

function ispconfig3_ChangePassword($params) { //todo: have stats and ftp password change with client update
	$soapuser = $params['configoption1'];
	$soappass = $params['configoption2'];
	$soapurl = $params['configoption3'];
	$usessl = $params['configoption4'];
	
	$accountid = $params['accountid'];
	$productid = $params['pid'];
	$domain = strtolower($params['domain']);
	$clientdetails = $params['clientsdetails'];
	
	$fullname = htmlspecialchars_decode($clientdetails['firstname']).' '.htmlspecialchars_decode($clientdetails['lastname']);
	$companyname = htmlspecialchars_decode($clientdetails['companyname']);
	$address = $clientdetails['address1'];
	if (!empty($clientdetails['address2'])) {
		$address = $clientdetails['address1'] . $clientdetails['address2'];
	}
	$zip = $clientdetails['postcode'];
	$city = $clientdetails['city'];
	$state = $clientdetails['state'];
	$country = $clientdetails['country'];
	$email = $clientdetails['email'];
	$phonenumber = $clientdetails['phonenumberformatted'];
	
	$username = $params['username'];
	$password = $params['password'];
	$templateid = $params['configoption5'];
	$shellaccess = $params['configoption6'];
	$createweb = $params['configoption7'];
	$phpmode = $params['configoption8'];
	$webstorage = $params['configoption9'];
	$webtraffic = $params['configoption10'];
	$params['configoption11'] == 'on' ? $webenablecgi = 'y' : $webenablecgi = '';
	$params['configoption12'] == 'on' ? $webenablessi = 'y' : $webenablessi = '';
	$params['configoption13'] == 'on' ? $webenableruby = 'y' : $webenableruby = '';
	$params['configoption14'] == 'on' ? $webenablesuexec = 'y' : $webenablesuexec = '';
	$webautosubdomain = $params['configoption15'];
	$webcreateftp = $params['configoptions16'];
	$createdns = $params['configoption17'];
	$dnstemplateid = $params['configoption18'];
	$createmail = $params['configoption19'];
	
	$active = $params['configoption20'];
	
	logModuleCall('ispconfig3','CreateClient',$params['clientdetails'],$params,'','');
	
	if ($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl . '/remote/index.php';
		$soap_uri = 'https://' . $soapurl . '/remote/';
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl . '/remote/index.php';
		$soap_uri = 'http://' . $soapurl . '/remote/';
	}
	
	if ((isset($username) && $username != '') && (isset($password) && $password != '')) {
		try {
			
			$soap_client = new SoapClient(
				null,
				array( 'location' => $soap_url,
					'uri' => $soap_uri,
					'exceptions' => 1,
					'stream_context' => stream_context_create(
						array('ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false)
							)
					),
					'trace' => false
				)
			);
			/* Authenticate and get serverID's from WHMCS */
			$soap_session_id = $soap_client->login($soapuser,$soappass);
			$client_id = $soap_client->client_get_by_username($soap_session_id,$username);
			$result = $soap_client->client_change_password($soap_session_id,$client_id['client_id'],$password);
			logModuleCall('ispconfig3','ChangePassword',$clientdetails,$result.'Password: '.$password);
			
			$soap_client->logout($soap_session_id);
			
			if($result == 1) {
				$successful = '1';
			}
			else {
				$successful = '0';
				$result = 'Password change failed';
			}
		}
		catch (SoapFault $e) {
			$error = 'SOAP Error: ' . $e->getMessage();
			$successful = '0';
		}
		
		if($successful == 1) {
			$result = 'success';
		}
		else {
			$result = 'Error: ' . $error;
		}
	}
	else {
		$result = 'Username or Password is blank or not set';
	}
	
	return $result;
}

function ispconfig3_LoginLink($params) {
    $soapurl = $params['configoption3'];
    $usessl = $params['configoption4'];

	if($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl;
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl;
	}
		
    return '
    <button type="button" class="btn btn-xs btn-success" onclick="ispconfigLogin">Login to Controlpanel</button>
    <script type="text/javascript">
		function ispconfigLogin() {
			$.ajax({
				type: "post",
				url: "'.$soap_url.'./login/index.php",
				data: "s_mod=login&s_pg=index&username='.$params['username'].'&passwort='.$params['password'].'",
				xhrFields: {withCredentials: true}
			}).done(function(){ location.href=\''.$soap_url.'/index.php\' });
		}
    </script>';
}

function ispconfig3_ClientArea($params) {
    $soapurl = $params['configoption3'];
    $usessl = $params['configoption4'];

	if ($usessl == 'on') {
		
		$soap_url = 'https://' . $soapurl;
	
	}
	else {
		
		$soap_url = 'http://' . $soapurl;
	}
	
    return '
    <button type="button" class="btn btn-xs btn-success" onclick="ispconfigLogin">Login to Controlpanel</button>
    <script type="text/javascript">
		function ispconfigLogin() {
			$.ajax({
				type: "post",
				url: "'.$soap_url.'./login/index.php",
				data: "s_mod=login&s_pg=index&username='.$params['username'].'&passwort='.$params['password'].'",
				xhrFields: {withCredentials: true}
			}).done(function(){ location.href=\''.$soap_url.'/index.php\' });
		}
    </script>';
}

?>
