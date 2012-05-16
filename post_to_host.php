<?
/* Copyright(c) DIYism (email/msn/gtalk:kexianbin@diyism.com web:http://diyism.com)
* Licensed under GPL (http://www.opensource.org/licenses/gpl-license.php) license.
*
* Version: kc3v
* Docs: http://code.google.com/p/post-to-host/
*/
function post_to_host($url, $data, $cook, $ref, &$resp_head, $type=1)
         {if (isset($_SESSION))
             {session_write_close();
             }
          $url = @parse_url($url);
          if (!$url)
             {return 'POST EXCEPTION: Could not parse url!';
             }
          if (!isset($url['port']))
             {$url['port'] = "";
             }
          if (!isset($url['query']))
             {$url['query'] = "";
             }

          $custom_headers=array();
          foreach ($data as $k=>$v)
                  {if (preg_match('/^HEADER:(.+)/', $k, $matches))
                      {$custom_headers[$matches[1]]=strtr($v, array("\r"=>'%0D', "\n"=>'%0A'));
                       unset($data[$k]);
                      }
                  }
          reset($data);

          $encoded = "";
          if ($type===1)
             {while (list($k,$v) = each($data))
                    {if (!is_array($v))
                        {$v=array($v);
                        }
                     foreach ($v as $vv)
                             {$encoded .= ($encoded ? "&" : "");
                              $encoded .= rawurlencode($k)."=".rawurlencode($vv);
                             }
                    }
             }
          else if ($type===2)
               {$boundary='---------------------------'.substr(md5(rand(0,32000)),0,10);
                while (list($k, $v) = each($data))
                      {if (!is_array($v))
                          {$v=array($v);
                          }
                       foreach ($v as $vv)
                               {$encoded .= "--$boundary\r\n";
                                if (preg_match('/^FILE:(.+)/', $k, $matches))
                                   {$file_name=basename($vv);
                                    $encoded .= "Content-Disposition: form-data; name=\"{$matches[1]}\"; filename=\"{$file_name}\"\r\n";
                                    $encoded .= "Content-Type: application/octet-stream\r\n\r\n";
                                    $encoded .= @file_get_contents($vv)."\r\n";
                                   }
                                else
                                    {$encoded .= "Content-Disposition: form-data; name=\"".$k."\"\r\n\r\n".$vv."\r\n";
                                    }
                               }
                      }
                $encoded.="--$boundary--\r\n";
               }
          else if ($type===3 || is_string($type))
               {$encoded.=@file_get_contents(current($data));
               }

          $str_cook = "";
          while (list($k,$v) = each($cook))
                {$str_cook .= ($str_cook ? "; " : "");
                 $str_cook .= $k."=".encode_cookie_value($v);
                }

          $specified_host=explode(':', @$GLOBALS['POST_TO_HOST.HOSTS'][$url['host']]);
          $ip=@$specified_host[0]?$specified_host[0]:$url['host'];
          $pre=(!@$specified_host[1] && $url['scheme']=='https')?'ssl://':'';
          $port_default=$url['scheme']=='https'?'443':'80';
          $port=$url['port']?$url['port']:$port_default;
          $real_port=@$specified_host[1]?$specified_host[1]:$port;

          set_error_handler(create_function('$err_no, $err_str, $err_file, $err_line', 'throw new Exception($err_str, $err_no);/*E_ERROR, E_WARNING, E_PARSE, E_NOTICE*/'));
          try
             {$fp=fsockopen($pre.$ip, $real_port);
             }
          catch (Exception $e)
                {$fp=false;
                }
          restore_error_handler();
          if (!$fp)
             {return 'POST EXCEPTION: Failed to open socket to '.$url['host'];
             }

          fputs($fp, ($type?(is_string($type)?$type:'POST'):'GET')." "
                     .$url['scheme'].'://'.$url['host'].(@$url['port']?':'.$url['port']:'')
                     .(@$url['path']?$url['path']:'/').(@$url['query']?'?'.$url['query']:'')
                     ." HTTP/1.1\r\n"
               );
          fputs($fp, "Host: {$url['host']}:{$port}\r\n");
          @$custom_headers['User-Agent']?'':fputs($fp, "User-Agent: Mozilla/5.0 (Windows NT 5.1; rv:8.0) Gecko/20100101 Firefox/8.0\r\n");
          fputs($fp, "Accept: */*\r\n");    //google check the 3 "Accept"
          fputs($fp, "Accept-Language: *\r\n");
          fputs($fp, "Accept-Encoding: *\r\n");
          fputs($fp, "Referer: $ref\r\n");
          fputs($fp, "Cookie: $str_cook\r\n");
          if ($type===1) //POST
             {fputs($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
             }
          else if ($type===2)  //POST with file
               {fputs($fp, "Content-Type: multipart/form-data; boundary={$boundary}\r\n");
               }
          else if ($type===3 || is_string($type))  //RAW POST with only file
               {if (!$custom_headers['Content-Type'])
                   {fputs($fp, "Content-Type: application/octet-stream\r\n");
                   }
               }
          foreach ($custom_headers as $k=>$v)
                  {fputs($fp, "{$k}: {$v}\r\n");
                  }
          fputs($fp, (@$specified_host[1]?"Proxy-":"")."Connection: close\r\n");
          fputs($fp, "Content-Length: " . strlen($encoded) . "\r\n\r\n");
          fputs($fp, "$encoded\r\n");

          $line = @fgets($fp);//如果前面fputs的信息有误,服务器无法立即返回EOF, 可能会等20秒
          if (preg_match('/^HTTP\/1\.. 404/i', $line))
             {return 'POST EXCEPTION: Url not exist!';
             }

          $results = "";
          $inheader = 1;
          $resp_head=array();
          while (!feof($fp))
                {$line = fgets($fp);
                 if ($inheader)
                    {if ($line == "\n" || $line == "\r\n")
                        {$inheader = 0;
                        }
                     else
                         {$resp_head[] = $line;
                         }
                    }
                 elseif (!$inheader)
                        {$results .= $line;
                        }
                }
          fclose($fp);
          if (isset($_SESSION))
             {@session_start();
             }
          if (strpos(strtolower(var_export($resp_head, true)), "transfer-encoding: chunked")!==false)
             {$results=unchunk($results);
             }
          if (strpos(strtolower(var_export($resp_head, true)), "content-encoding: gzip")!==false)
             {$results=gzinflate(substr($results, 10, -8));//gzdecode($results);
             }
          else if (strpos(strtolower(var_export($resp_head, true)), "content-encoding: deflate")!==false)
               {$results=gzinflate($results);
               }
          return $results;
         }

function encode_cookie_value($value)
         {return strtr($value,
                       array_combine(str_split($tmp=",; \t\r\n\013\014"),
                                     array_map('rawurlencode', str_split($tmp))
                                    )
                      );
         }

function unchunk($result)
         {return preg_replace('/([0-9A-F]+)\r\n(.*)/sie',
                              '($cnt=@base_convert("\1", 16, 10))
                               ?substr(($str=@strtr(\'\2\', array(\'\"\'=>\'"\', \'\\\\0\'=>"\x00"))), 0, $cnt).unchunk(substr($str, $cnt+2))
                               :""
                              ',
                              $result
                             );
         }

function post_to_host_retry($url, $data, $cook, $ref, &$resp_head, $type=1)
         {for ($i=0; $i<3; ++$i)
              {$rtn=post_to_host($url, $data, $cook, $ref, $resp_head, $type);
               if (substr($rtn, 0, 16)!=='POST EXCEPTION: ')
                  {break;
                  }
              }
          return $rtn;
         }

function get_cookies_from_heads($heads)
         {$cookies=array();
          for ($i=0,$cnt=count($heads);$i<$cnt;++$i)
              {$heads[$i]=rtrim($heads[$i], "\r\n");
               $pos=strpos($heads[$i],'Set-Cookie: ');
               if ($pos!==false)
                  {$pos_end=strpos($heads[$i],';',$pos);
                   if ($pos_end===false)
                      {$pos_end=strlen($heads[$i]);
                      }
                   $tmp=substr($heads[$i],$pos+12,$pos_end-$pos-12);
                   if (!$tmp)
                      {continue;
                      }
                   $pos1=strpos($tmp,'=');
                   $cookies[substr($tmp, 0, $pos1)]=''.substr($tmp, $pos1+1);
                  }
              }
           return $cookies;
         }

function get_from_heads($heads, $name)
         {$rtn=explode(strtolower($name).': ', strtolower(var_export($heads, true)));
          $rtn=explode("\n", @$rtn[1]);
          return trim(@$rtn[0]);
         }
?>