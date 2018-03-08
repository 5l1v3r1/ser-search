<?php

/**
 * Find data serialization with curl, the easy way
 * 
 * by @proclnas
 */

error_reporting(0);
set_time_limit(0);

if (!extension_loaded('pthreads')) exit ("[-] Pthreads not found, extension needed\n");

function openFile($file) {
	$fp = fopen($file, 'r');
	while (!feof($fp)) {
		yield trim(fgets($fp));
	}
}

$opt    = getopt('f:o:t:');
$output = isset($opt['o']) ?: 'valid-output.txt';
$pool   = new Pool(isset($opt['t']) ? $opt['t'] : 1);

if (!isset($opt['f'])) exit (sprintf("php %s -f site-list -o output -t threads\n", $argv[0]));
if (!file_exists($opt['f'])) exit (sprintf("[-] File %s not found\n", $opt['f']));

foreach (openFile($opt['f']) as $line) {
	$pool->submit(new class($line, $output) extends Threaded {
		/**
		 * Url to get
		 * @var string
		 */
		private $url;

		/**
		 * Output to write results
		 * 
		 * @var string
		 */
		private $output;

		/**
		 * Http response
		 * @var string
		 */
		private $httpResponse;

		/**
		 * Verbose mode
		 * 
		 * @var bool
		 */
		private $verbose;

		public function __construct($url, $output) {
			$this->url = $url;
			$this->output = $output;
			$this->verbose = false;
		}

		/**
		 * HTTP GET REQUEST
		 * 
		 * @return void
		 */
		private function getRequest() {
			$ch = curl_init();

			curl_setopt_array($ch, [
				CURLOPT_URL            => $this->getUrl(),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_VERBOSE        => $this->getVerbose()
			]);

			$this->httpResponse = curl_exec($ch);
			curl_close($ch);
		}

		/**
		 * Return url
		 * 
		 * @return string
		 */
		public function getUrl() {
			return $this->url;
		}

		/**
		 * Get verbose mode
		 * 
		 * @return string
		 */
		public function getVerbose() {
			return $this->verbose;
		}

		/**
		 * Get HTTP response
		 * @return string
		 */
		public function getHttpResponse() {
			return $this->httpResponse;
		}

		/**
		 * Start thread
		 *
		 * @return void
		 */
		public function run() {
			$this->getRequest();
			$response            = $this->getHttpResponse();
			$hasCookieSerialized = false;
			$hasDataSerialized   = false;
			$hostResume = [
				'url' => $this->getUrl(),
				'serialization' => false,
				'cookies' => false,
				'data' => false
			];

			// Always decode response to get original cookie value
			if (preg_match('#Set-Cookie: (.+)\n#', urldecode($response), $match)) {
		        $cookieValues = $match[1];
		        if (preg_match('#a:\d+:{(i|s):(\d|\s);#', $cookieValues, $cookieMatch)) {
		        	$hasCookieSerialized = true;
		        	$hostResume['serialization'] = true;
		        	$hostResume['cookies'] = true;
		        }
			}

			// Match serialization with object and arrays
			if (preg_match('#a:\d+:{(i|s):(\d|\s);#', $response, $match)) {
				$hasDataSerialized = true;
				$hostResume['serialization'] = true;
				$hostResume['data'] = true;
			}

			$msg = sprintf(
				"[%s] Data serialized: %s, Cookie serialized: %s\n",
				$hostResume['url'],
				$hostResume['cookies'] ? '[+]' : '[-]',
				$hostResume['data'] ? '[+]' : '[-]'
			);

			if ($hostResume['serialization']) {
				file_put_contents(
					$this->output, 
					$msg,
					LOCK_EX | FILE_APPEND
				);
			}

			echo $msg;
		}
	});
}

while ($pool->collect()) continue;
$pool->shutdown();