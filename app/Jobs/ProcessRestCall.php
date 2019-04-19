<?php

namespace App\Jobs;

use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessRestCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $host, $params,  $url, $request, $token;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $host, array $params,  string $url, array $request)
    {
        $this->host = $host;
        $this->params = $params;
        $this->url = $url;
        $this->request = $request;

        $client = new Client();

        $response = $client->post($this->host.'oauth/token', [
            'form_params' => $this->params,
        ]);

        $this->token = json_decode((string) $response->getBody(), true)['access_token'];

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $client = new Client();

        $response = $client->post($this->url, [
            'headers'=>[
                'Authorization' =>  'Bearer '.$this->token,
            ],
            'form_params' => $this->request,
        ]);


        /*$fp = fopen('a.txt', 'w');
        fprintf($fp, '%s', $response->getBody(). '\n\n' . json_encode($this->request));
        fclose($fp);*/

    }
}
