<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class CannotRemoveStoresToFriendGroupException extends Exception
{
    protected $message = 'You do not have permissions to remove stores from this group';

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
