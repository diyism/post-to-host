post-to-host
============

comprehensive fsockopen-based HTTP request function,support GET,POST,POST with file,raw POST,POST with specified IP etc.

post_to_host.php to replace curl with fsockopen:

variable prefix explanation:

    'arr' means any array, such as: array('var1'=>'val1', 'var2'=>'val2'), or empty one: array()
    'ref_arr' means any array returned after post_to_host executed, you don't need define it in advance, print it:var_export($ref_arr_head)
    'str' means any string, such as: 'http://www.google.com', or empty one: ''


//GET:

    $str_rtn=post_to_host($str_url_target, array(), $arr_cookie, $str_url_referer, $ref_arr_head, 0);

//POST:

    $arr_params=array('para1'=>'...', 'para2'=>'...');
    $str_rtn=post_to_host($str_url_target, $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head);

//POST with file:

    $arr_params=array('para1'=>'...', 'FILE:para2'=>'/tmp/test.jpg', 'para3'=>'...');
    $str_rtn=post_to_host($str_url_target, $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head, 2);

//raw POST with file:

    $str_rtn=post_to_host($str_url_target, array('/tmp/test.jpg'), $arr_cookie, $str_url_referer, $ref_arr_head, 3);

//get cookie and merge cookies:

    $arr_new_cookie=get_cookies_from_heads($ref_arr_head)+$arr_old_cookie;//don't change the order

//get redirect url:

    $str_url_redirect=get_from_heads($ref_arr_head, 'Location');

//POST with custom headers:

    $arr_params=array('para1'=>'...', 'para2'=>'...', 'HEADER:username'=>'peter', 'HEADER:password'=>'google');
    $str_rtn=post_to_host($str_url_target, $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head);

//raw POST with custom headers:

    $str_file_name=array_search('uri', @array_flip(stream_get_meta_data($GLOBALS[mt_rand()]=tmpfile())));
    $arr_params=array('para1'=>'...', 'para2'=>'...');
    file_put_contents($str_file_name, json_encode($arr_params));
    $arr_params=array($str_file_name, 'HEADER:Content-Type'=>'application/json');
    $str_rtn=post_to_host($str_url_target, $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head, 3);

//POST with specified IP:

    $GLOBALS['POST_TO_HOST.HOSTS']['www.domain1.com']=gethostbyname('www.domain1.com');//or '11.12.13.14'
    post_to_host('http://www.domain1.com/login.php', $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head);

//POST with specified HTTP proxy domain or HTTP proxy IP:

    $GLOBALS['POST_TO_HOST.HOSTS']['www.domain1.com']='www.proxy1.com:8080';//or '11.12.13.14:8080'
    post_to_host('http://www.domain1.com/login.php', $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head);

//POST with specified SOCKS5 proxy domain or SOCKS5 proxy IP:

    $GLOBALS['POST_TO_HOST.HOSTS']['www.domain1.com']='www.proxy1.com:8080:SOCKS5';//or '11.12.13.14:8080:SOCKS5'
    post_to_host('http://www.domain1.com/login.php', $arr_params, $arr_cookie, $str_url_referer, $ref_arr_head);

//set total timeout(default total timeout is 60):

    $GLOBALS['POST_TO_HOST.TOTAL_TIMEOUT']=20;

//set connection timeout and line read/write timeout(default is 5):

    $GLOBALS['POST_TO_HOST.LINE_TIMEOUT']=10;

//set https CA file to verify HTTPS peer certificate:

    $GLOBALS['POST_TO_HOST.HTTPS_VERIFY_PEER_CA']='ca-bundle.crt';

//get cookies against url/host:

    $arr_new_cookie=get_cookies_from_heads($ref_arr_head, $request_url)+$arr_old_cookie;//don't change the order

//finally, file_get_contents+stream_context_create+$http_response_header is more convenient thant post_to_host:

    $context=stream_context_create(array('http'=>array('method'=>'POST',
                                                       'timeout'=>15,
                                                       'header'=>"Host: test.appspot.com\r\n",
                                                       'content'=>http_build_query($arr_data)
                                                      ),
                                         'ssl'=>array('verify_peer'=>true,
                                                      'cafile'=>'../ca-bundle.crt'
                                                     )
                                        )
                                  );
    echo file_get_contents('https://74.125.128.103', false, $context);

//best

    function curl_to_host($method, $url, $headers, $data, &$resp_headers, $total_timeout=20, $proxy='')
             {$ch=curl_init($url);
              curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, @$GLOBALS['POST_TO_HOST.LINE_TIMEOUT']?$GLOBALS['POST_TO_HOST.LINE_TIMEOUT']:5);
              curl_setopt($ch, CURLOPT_TIMEOUT, $total_timeout);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
              curl_setopt($ch, CURLOPT_HEADER, 1);

              if ($proxy)
                 {$proxy=explode(':', $proxy);
                  curl_setopt($ch, CURLOPT_PROXYTYPE, @$proxy[2]==='SOCKS5'?CURLPROXY_SOCKS5:CURLPROXY_HTTP);
                  curl_setopt($ch, CURLOPT_PROXY, $proxy[0].':'.$proxy[1]);
                 }

              if (stripos($url, 'https')===0)
                 {curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                 }

              if ($method=='POST')
                 {curl_setopt($ch, CURLOPT_POST, true);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                 }
              foreach ($headers as $k=>$v)
                      {$headers[$k]=str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $k)))).': '.$v;
                      }
              curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
              $rtn=curl_exec($ch);
              curl_close($ch);

              $rtn=explode("\r\n\r\nHTTP/", $rtn, 2);    //to deal with "HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK...\r\n\r\n..." header
              $rtn=(count($rtn)>1 ? 'HTTP/' : '').array_pop($rtn);
              list($str_resp_headers, $rtn)=explode("\r\n\r\n", $rtn, 2);

              $str_resp_headers=explode("\r\n", $str_resp_headers);
              array_shift($str_resp_headers);    //get rid of "HTTP/1.1 200 OK"
              $resp_headers=array();
              foreach ($str_resp_headers as $k=>$v)
                      {$v=explode(': ', $v, 2);
                       $resp_headers[$v[0]]=$v[1];
                      }

              return $rtn;
             }
