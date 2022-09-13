<?php declare(strict_types=1);

namespace RtspChecker;

class RtspClient {

  private int $cSequence = 1;

  private array $authentication = [];
  private array $options = [];
  private string $user = '';
  private string $password = '';

  private  $socket;


  public function init(string $url): bool {
    $parsed = parse_url($url);

    if(isset($parsed['user'])) $this->user = $parsed['user'];
    if(isset($parsed['pass'])) $this->password = $parsed['pass'];

    $this->options = [
      'User-Agent' => 'LibVLC/3.0.17.3 (LIVE555 Streaming Media v2016.11.28)',
      'Accept'     => 'application/sdp',
    ];

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

    socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 10, 'usec' => 0]);
    socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 10, 'usec' => 0]);

    $this->socket = $socket;

    return socket_connect($this->socket, $parsed['host'], $parsed['port']);
  }

  public function disconnect(): bool {
    if ($this->socket) {
      socket_close($this->socket);
    }

    return true;
  }

  /**
   * @param string $user
   */
  public function setUser(string $user): void {
    $this->user = $user;
  }

  /**
   * @param string $password
   */
  public function setPassword(string $password): void {
    $this->password = $password;
  }

  public function send($method, $uri): array {
    $request = $method." ".$uri." RTSP/1.0\r\n";
    $request .= "CSeq: ".$this->cSequence++."\r\n";

    foreach ($this->options as $k => $v) {
      $request .= $k.": ".$v."\r\n";
    }
    $request .= "\r\n";

    socket_write($this->socket, $request);

    $parsedResponse = '';
    while ($r = socket_read($this->socket, 2048)) {
      $parsedResponse .= $r;
      if (strpos($parsedResponse, "\r\n\r\n")) {
        break;
      }
    }

    return $this->interpretResponse($parsedResponse);
  }

  public function authenticateBasic() {
    $this->options['Authorization'] = sprintf('Basic %s', base64_encode($this->user.":".$this->password));
  }

  public function detectRoute($ip, $port): ?array {
    foreach ($this->routes() as $key => $route) {
      $uri = sprintf('rtsp://%s:%s%s', $ip, $port, $route);
      try {
        $response = $this->send('DESCRIBE', $uri);
        if ($response['headers']['code'] == '404') {
          continue;
        }

        return [
          'route'    => $uri,
          'response' => $response,
        ];
      } catch (\Exception $e) {
        echo "ERROR MSG:".$e->getCode()."\n";
      }
    }

    return null;
  }

  public function authenticateFromResponse(string $uri, string $method, array $response) {
    $str = $response['WWW-Authenticate'];
    $parsed = preg_split("/\s/", $str);

    $p = parse_url($uri);
    if(isset($p['user']) && isset($p['pass'])) {
      $this->user = $p['user'];
      $this->password = $p['pass'];
    }

    $authType = $parsed[0];
    unset($parsed[0]);

    $str = substr($str, strlen($authType));
    $str = str_replace('"', "'", $str);
    preg_match_all("/([^,= ]+)='([^,]+)'/", $str, $r);
    $result = array_combine($r[1], $r[2]);

    $this->authentication = [
      'authtype' => $authType,
      'realm'    => $result['realm'],
      'nonce'    => $result['nonce'] ?? null,
      'uri'      => $uri,
    ];

    $authorisation = match ($authType) {
      'Digest' => sprintf(
        '%s username="%s", realm="%s", nonce="%s", uri="%s", response="%s"',
        $authType,
        $this->user,
        $this->authentication['realm'],
        $this->authentication['nonce'],
        $this->authentication['uri'],
        $this->calculateDigestHash($method)
      ),
      default => sprintf('Basic %s', base64_encode($this->user.":".$this->password)),
    };

    $this->options['Authorization'] = $authorisation;
  }

  public function authenticateAndDescribe(string $url, string $user, string $password, array $response): array {
    $method = 'DESCRIBE';

    $this->setUser($user);
    $this->setPassword($password);
    $this->authenticateFromResponse(
      $url,
      $method,
      $response
    );

    return $this->send($method, 'rtsp://93.125.0.74:554/');
  }

  private function calculateDigestHash($method): string {
    $str1 = $this->user.":".$this->authentication['realm'].":".$this->password;
    $str2 = $method.":".$this->authentication['uri'];

    $ha1 = hash("md5", $str1);
    $ha2 = hash("md5", $str2);

    return hash("md5", $ha1.":".$this->authentication['nonce'].":".$ha2);
  }

  private function interpretResponse($response): array {
    $result = [];
    $blocks = preg_split('/\r\n\r\n/', $response);

    if (isset($blocks[0])) {
        $result['headers'] = $this->parseResponseHeaders(preg_split('/\r\n/', $blocks[0]));
    }

    if (isset($blocks[1])) {
        $result['sdp'] = preg_split("/(\r\n|\n|\r)/", $blocks[1]);
    }


    return $result;
  }

  private function parseResponseHeaders(array $lines): array {
      $result = [];
      foreach ($lines as $k => $v) {
          if ($k == 0) {
              $r = preg_split('/ /', $v);
              $result['proto'] = (isset($r[0])) ? $r[0] : '';
              $result['code'] = (isset($r[1])) ? $r[1] : '';
              $result['msg'] = (isset($r[2])) ? $r[2] : '';
          } else {
              $r = preg_split('/: /', $v);
              if (isset($r[0])) {
                  $result[$r[0]] = (isset($r[1])) ? $r[1] : '';
              }
          }
      }

      return $result;
  }

}


