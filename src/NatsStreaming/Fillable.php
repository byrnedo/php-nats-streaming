<?php

namespace NatsStreaming;

use Traversable;

trait Fillable
{


    //private $fillable = [];
    /**
     * Initialize the parameters.
     *
     * @param Traversable|array $options The connection options.
     *
     * @throws Exception When $options are an invalid type.
     *
     * @return void
     */
    protected function initialize($options)
    {
        if (is_array($options) === false && ($options instanceof Traversable) === false) {
            throw new Exception('The $options argument must be either an array or Traversable');
        }

        foreach ($options as $key => $value) {
            if (in_array($key, $this->fillable, true) === false) {
                continue;
            }

            $method = 'set'.ucfirst($key);

            if (method_exists($this, $method) === true) {
                $this->$method($value);
            }
        }
    }
}
