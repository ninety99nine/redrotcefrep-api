<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class CannotRemoveYourselfAsStoreCreatorException extends Exception
{
    protected $message = 'You are not allowed to remove yourself as the store creator';

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
