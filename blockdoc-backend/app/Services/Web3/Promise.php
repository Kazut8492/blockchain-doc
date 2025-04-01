<?php

namespace App\Services\Web3;

class Promise
{
    protected $executor;
    protected $result;
    protected $error;
    protected $fulfilled = false;
    protected $rejected = false;

    public function __construct(callable $executor)
    {
        $this->executor = $executor;
    }

    public function wait()
    {
        try {
            call_user_func($this->executor, 
                function ($value) {
                    $this->result = $value;
                    $this->fulfilled = true;
                },
                function ($reason) {
                    $this->error = $reason;
                    $this->rejected = true;
                }
            );

            if ($this->rejected && $this->error instanceof \Exception) {
                throw $this->error;
            } else if ($this->rejected) {
                throw new \Exception($this->error);
            }

            return $this->result;
        } catch (\Exception $e) {
            throw $e;
        }
    }
}