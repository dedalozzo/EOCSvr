<?php

//! @file MapDocCmd.php
//! @brief This file contains the MapDocCmd class.
//! @details
//! @author Filippo F. Fadda



namespace Commands;


//! @brief Maps a document against every single map function stored into the server.
//! @details When the view function is stored in the server, CouchDB starts sending in all the documents in the
//! database, one at a time. The server calls the previously stored functions one after another with the document
//! and stores its result. When all functions have been called, the result is returned as a JSON string.<br />
//! The argument provided by CouchDB has the following structure:
//! Array
//! (
//!     [0] => Array
//!     (
//!         [_id] => 32012
//!         [_rev] => 1-f19919e544340438babac6cc86ec61d5
//!         [idItem] => 32012
//!         [title] => Visual Modelling with Rational Rose 2000 and UML
//!     )
//! )
class MapDocCmd extends AbstractCmd {
  const MAP_DOC = "map_doc";


  public final static function getName() {
    return self::MAP_DOC;
  }


  public final function execute() {
    $doc = self::arrayToObject(reset($this->args));

    // We use a closure here, so we can just expose the emit() function to the closure provided by the user. We have
    // another advantage here: the $map variable is defined inside execute(), so we don't need to declare it as class member.
    $emit = function($key = NULL, $value = NULL) use (&$map) {
      $map[] = array($key, $value);
    };

    $closure = NULL; // This initialization is made just to prevent a lint error during development.

    $result = []; // Every time we map a document against all the registered functions we must reset the result.

    foreach ($this->server->getFuncs() as $fn) {
      $map = []; // Every time we map a document against a function we must reset the map.

      // Here we call the closure function stored in the view. The $closure variable contains the function implementation
      // provided by the user. You can have multiple views in a design document and for every single view you can have
      // only one map function.
      // The closure must be declared like:
      //
      //     function($doc) use ($emit) { ... };
      //
      // This technique let you use the syntax '$emit($key, $value);' to emit your record. The function doesn't return
      // any value. You don't need to include any files since the closure's code is executed inside this method.
      eval("\$closure = ".$fn);

      if (is_callable($closure)) {
        call_user_func($closure, $doc);
        $result[] = $map;
      }
      else
        throw new \Exception("The function you provided is not callable.");
    }

    // Sends mappings to CouchDB.
    $this->server->writeln(json_encode($result));
  }


  // @brief Converts the array to an object.
  // @return object
  public final static function arrayToObject($array) {
    return is_array($array) ? (object)array_map(__METHOD__, $array) : $array;
  }

}
