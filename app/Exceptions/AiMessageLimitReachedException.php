<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class AiMessageLimitReachedException extends Exception
{
    protected $message = 'Please subscribe to ask me more questions';

    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render()
    {
        return response(['message' => $this->message], Response::HTTP_FORBIDDEN);
    }
}
