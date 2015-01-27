<?php
/**

 */

namespace TestingCurlMulti;
include('adama.php');

use \Adama\Login as adama;

class curl_multi {

    protected $api_base = "http://t1qa2.mediamath.com/api/v2.0/";

    protected $curl_global_opts = array (
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_COOKIEJAR => 'cooks.txt',
                                    CURLOPT_COOKIEFILE => 'cooks.txt',
                                  );

    function register_adama_session(){

        $login_data = array('user'=>adama::user,
                            'password'=>adama::password,
                            'api_key'=>adama::api_key);

        $login_form_data = http_build_query($login_data);

        $ch_adama_login = curl_init();

        $verbose = fopen('php://temp', 'rw+');

        curl_setopt($ch_adama_login , CURLOPT_STDERR, $verbose);
        curl_setopt($ch_adama_login, CURLOPT_POST, true);
        curl_setopt($ch_adama_login, CURLOPT_URL, adama::login_url);
        curl_setopt($ch_adama_login, CURLOPT_POSTFIELDS, $login_form_data);
        curl_setopt_array($ch_adama_login, $this->curl_global_opts);

        $adama_login_response = curl_exec($ch_adama_login);

        if ($adama_login_response === FALSE) {
            printf("cUrl error (#%d): %s<br>\n", curl_errno($ch_adama_login ),
                htmlspecialchars(curl_error($ch_adama_login )));
        }

        curl_close($ch_adama_login);

    }

    function executeCurl(){
        $testing_url = "https://t1qa2.mediamath.com/api/v2.0/atomic_creatives/";

        $ch = curl_init();
        curl_setopt_array($ch, $this->curl_global_opts);

        curl_setopt($ch, CURLOPT_URL, $testing_url);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        $ch_response = curl_exec($ch);

        curl_close($ch);

        print_r($ch_response);

    }

    function executeMultiCurl(){

        // GET that returns a gazillion entities. ~110k, page limited by 100.
        $testing_url = "https://t1qa2.mediamath.com/api/v2.0/atomic_creatives/";

        $results = null;

        $mch = curl_multi_init();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $testing_url);
        curl_setopt_array($ch, $this->curl_global_opts);

        curl_multi_add_handle($mch, $ch);

        $curl_multi_running = NULL;

        do {
            $mrc = curl_multi_exec($mch, $curl_multi_running);
            curl_multi_select($mch);
        } while ($curl_multi_running > 0);


        curl_multi_remove_handle($mch, $ch);

        curl_multi_close($mch);

        $results = curl_exec($ch);

        $this->parseMultiXML($results);

    }

    function parseMultiXML($results){

        $_sx = simplexml_load_string ($results, 'SimpleXmlElement', LIBXML_NOERROR+LIBXML_ERR_FATAL+LIBXML_ERR_NONE);

        if (false == $_sx){
            echo "ADAMA DIDN'T RETURN XML, SORRY";
            return;
        }

        if (!isset( $_sx->entities['count'] ) ){
            echo "apparently no entities? That's weird.";
        }

        echo($_sx->entities['count'] . "\n");

        echo( ceil($_sx->entities['count'] / 100));

    }

}

//$login = new curl_multi();
//$login->register_adama_session();

//$testing_curl = new curl_multi();
//$testing_curl->executeCurl();

$testing_multi = new curl_multi();
$testing_multi->executeMultiCurl();