<?php

namespace Blockchain\Wallet;

use \Blockchain\Blockchain;
use \Blockchain\Exception\CredentialsError;
use \Blockchain\Exception\ParameterError;

class Wallet {
    private $identifier = null;
    private $main_password = null;
    private $second_password = null;

    public function __construct(Blockchain $blockchain) {
        $this->blockchain = $blockchain;
    }

    public function credentials($id, $pw1, $pw2=null) {
        $this->identifier = $id;
        $this->main_password = $pw1;
        if(!is_null($pw2)) {
            $this->second_password = $pw2;
        }
    }

    private function _checkCredentials() {
        if(is_null($this->identifier) || is_null($this->main_password)) {
            throw new CredentialsError('Please enter wallet credentials.');
        }
    }

    private function reqParams($extras=array()) {
        $ret = array('password'=>$this->main_password);
        if(!is_null($this->second_password)) {
            $ret['second_password'] = $this->second_password;
        }

        return array_merge($ret, $extras);
    }

    private function url($resource) {
        return "merchant/" . $this->identifier . "/" . $resource;
    }

    private function call($resource, $params=array()) {
        $this->_checkCredentials();
        return $this->blockchain->post($this->url($resource), $this->reqParams($params));
    }

    public function getIdentifier() {
        return $this->identifier;
    }

    public function getBalance() {
        $json = $this->call('balance');
        return \Blockchain\Conversion\Conversion::BTC_int2str($json['balance']);
    }

    public function getAddressBalance($address) {

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, 'https://api.blockcypher.com/v1/btc/main/addrs/'.$address.'/balance');
        curl_setopt($c, CURLOPT_TIMEOUT, 15);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        $address_balance = curl_exec($c);
        $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c); 
        $returnxpre = json_decode($address_balance); 
        $return= new \stdClass;
        $return->address= $returnxpre->address;
        $return->balance = number_format($returnxpre->final_balance / 100000000,8);
        $return->balance_pending= number_format($returnxpre->unconfirmed_balance /100000000,8);
        $return->total_received = number_format($returnxpre->total_received /100000000,8);
        $return->n_transactions= $returnxpre->final_n_tx; 
        return $return; 
        
    }
/*
    public function getAddressBalance_aviable($address)
    {
       
    }*/

    public function getAddresses() {
        $json = $this->call('list');
        $addresses = array();
        foreach ($json['addresses'] as $address) {
            $addresses[] = new WalletAddress($address);
        }
        return $addresses;
    }

    public function getNewAddress($label=null) {
        $params = array();
        if(!is_null($label)) {
            $params['label'] = $label;
        }
        return new WalletAddress($this->call('new_address', $params));
    }

    public function archiveAddress($address) {
        $json = $this->call('archive_address', array('address'=>$address));
        if(array_key_exists('archived', $json)) {
            if($json['archived'] == $address) {
                return true;
            }
        }
        return false;
    }

    public function unarchiveAddress($address) {
        $json = $this->call('unarchive_address', array('address'=>$address));
        if(array_key_exists('active', $json)) {
            if($json['active'] == $address) {
                return true;
            }
        }
        return false;
    }

    public function send($to_address, $amount, $from_address, $fee) {
        if(!isset($amount))
            throw new ParameterError("Amount required.");
            $c = curl_init();
            curl_setopt($c, CURLOPT_URL, 'https://api.blockchain.info/fees');
            curl_setopt($c, CURLOPT_TIMEOUT, 15);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            $satoshis_standar_fee_cant = curl_exec($c);
            $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
            curl_close($c); 
            $satoshis_standar_fee = json_decode($satoshis_standar_fee_cant);
            $satoshis_standar_fee = $satoshis_standar_fee->mempool / 10000000;
            $satoshis_standar_fee = number_format ($satoshis_standar_fee = ($satoshis_standar_fee / 10),8);

            if($fee == 'hight');
            {
                $fee_select = $satoshis_standar_fee;
                settype($fee_select,'string');
                
            }
            if  ($fee == 'default') 
            {
                 $fee_select = number_format($satoshis_standar_fee /2.5,8);
                 settype($fee_select,'string');
            }
            if ($fee != 'default' && $fee != 'hight')
            {
              $fee_select = $satoshis_standar_fee;
              settype($fee_select,'string');  
            }

        $params = array(
            'to'=>$to_address,
            'amount'=>\Blockchain\Conversion\Conversion::BTC_float2int($amount)
        );
        if(!is_null($from_address))
            $params['from'] = $from_address;
        if(!is_null($fee))
            $params['fee'] = \Blockchain\Conversion\Conversion::BTC_float2int($fee_select);

        return new PaymentResponse($this->call('payment', $params));
    }

    public function sendMany($recipients, $from_address=null, $fee=null) {
        $R = array();
        // Construct JSON by hand, preserving the full value of amounts
        foreach ($recipients as $address => $amount) {
            $R[] = '"' . $address . '":' . \Blockchain\Conversion\Conversion::BTC_float2int($amount);
        }
        $json = '{' . implode(',', $R) . '}';

        $params = array(
            'recipients'=>$json
        );
        if(!is_null($from_address))
            $params['from'] = $from_address;
        if(!is_null($fee))
            $params['fee'] = \Blockchain\Conversion\Conversion::BTC_float2int($fee);

        return new PaymentResponse($this->call('sendmany', $params));
    }
}