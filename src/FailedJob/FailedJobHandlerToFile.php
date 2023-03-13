<?php
namespace App\Rabbitmq\FailedJob;

use App\Rabbitmq\FailedJob\Contract\FailedJobHandlerInterface;
use App\Rabbitmq\Rabbit\Client;
use Illuminate\Support\Facades\Storage;

class FailedJobHandlerToFile implements FailedJobHandlerInterface
{
    public function write(array $data)
    {
        $path = storage_path('/failed_jobs/') . now() . ".txt";
        Storage::put($path, json_encode($data));
    }

    public function run()
    {
        $client = app(Client::class);
        $path = storage_path('/failed_jobs/');
        $files = Storage::allFiles($path);
        foreach ($files as $file){
            if (Storage::exists($file)){
                $data = json_decode(Storage::read($file));
                $array = json_decode(json_encode($data), true);
                $message = json_decode($array['message']['body'], true);
                $client->setMessage(
                    $message
                )->publish($array["queue"]);
            }
        }
    }
}