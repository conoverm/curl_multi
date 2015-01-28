<?php
/**

 */

namespace TestingCurlMulti;
include('adama.php');

use \Adama\Login as adama;

class curl_multi {


    public $adama_urls = array();

    public $adama_results = array();

    public $collection_to_get = "agencies";

    public $testing_url = adama::api_base;

    // This is the pointer for a list of entities. If there are 60 entities in a request, and the offset is
    // 20, then Adama will return the last 40 entities. Generally, this starts at 0 and grows by 100 each request.
    public $page_offset = 0;

    // Adama limits returning entities to a max of 100 per call.
    public $page_limit = 100;

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

    /**
     * Execute a single curl to find out if we need to execute more (which will lead to curl_multi).
     * Save the single executed curl in an array and return that so it's not executed twice.
     */
    function executeCurl(){

        $testing_url = adama::api_base.$this->collection_to_get;

        $ch = curl_init();
        curl_setopt_array($ch, $this->curl_global_opts);

        curl_setopt($ch, CURLOPT_URL, $testing_url);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);

        $ch_response = curl_exec($ch);

        curl_close($ch);

        $this->parseXML($ch_response);

    }

    function parseXML($testCurl){

        $_sx = simplexml_load_string($testCurl, 'SimpleXmlElement', LIBXML_NOERROR+LIBXML_ERR_FATAL+LIBXML_ERR_NONE);

        if (false == $_sx){
            echo "ADAMA DIDN'T RETURN XML, SORRY";
            return;
        }

        if (!isset( $_sx->entities['count'] ) ){
            echo "Apparently no entities? That's weird.";
            return $_sx;
        }

        // We have a successful request -- add the first page to the results array.
        $this->adama_results[0]=$testCurl;

        // if 'count' minus 'start' is greater than 100, we have to do an async curl to get the rest of the entities.
        if (($_sx->entities['count'] - $_sx->entities['start']) > 100){
            $this->buildUrlArray($_sx->entities['count'] );
        }

    }

    function buildUrlArray($entityCount){

        if ($entityCount < $this->page_limit){
            echo "\$entityCount is $entityCount" .' which is lower than the $page_offset: '."$this->page_offset".' , so we have all the entities.';
            // dump the results back to whatever is consuming it
            return;
        }

        for ($i = 0;  $i < $entityCount; $i+=100){
            // start the loop at 1 not 0, because the first URL is the URL already executed
            array_push($this->adama_urls, adama::api_base . $this->collection_to_get .'?'."page_limit=$this->page_limit&page_offset=$i");
            //echo($this->adama_urls[$i] . "\n");
        }

        $this->throttle_curl($this->adama_urls, $this->curl_global_opts);

    }

    function throttle_curl($urls, $options = null) {

        // make sure the rolling window isn't greater than the # of urls
        $throttle_multi = 5;

        if (count($urls) < $throttle_multi){
            $throttle_multi = count($urls);
        }

        $master = curl_multi_init();

        // start the first batch of requests
        for ($i = 0; $i < $throttle_multi; $i++) {
                $ch = curl_init();
                $options[CURLOPT_URL] = $urls[$i];
                curl_setopt_array($ch,$options);
                curl_multi_add_handle($master, $ch);
            }

        do {
            while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);

                if($execrun != CURLM_OK){
                    break;
                }

                while($done = curl_multi_info_read($master)) {

                    $info = curl_getinfo($done['handle']);

                    if ($info['http_code'] == 200)  {
                        $output = curl_multi_getcontent($done['handle']);

                        // request successful.
                        $this->sortOutput($output);

                        $ch = curl_init();

                        if ($i < count($urls)){
                            $options[CURLOPT_URL] = $urls[$i++];  // increment i
                            curl_setopt_array($ch,$options);
                            curl_multi_add_handle($master, $ch);

                            // remove the curl handle that just completed
                            curl_multi_remove_handle($master, $done['handle']);
                        } else {
                            break;
                        }

                    } else {
                        var_dump($info);
                    }
                }
        } while ($running);

        curl_multi_close($master);
        ksort($this->adama_results);
        return true;
    }

    function sortOutput($output){
        $_sx = simplexml_load_string($output, 'SimpleXmlElement', LIBXML_NOERROR+LIBXML_ERR_FATAL+LIBXML_ERR_NONE);
        $array_entry = $_sx->entities['start'] *.01;
        $this->adama_results[$array_entry] = $output;
    }


}

//$login = new curl_multi();
//$login->register_adama_session();

$testing_curl = new curl_multi();
$testing_curl->executeCurl();

//$testing_multi = new curl_multi();
//$testing_multi->executeMultiCurl();
