<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document(collection="users")
 */
class User
{
    /**
     * @MongoDB\Id
     */
    private $id;

    /**
     * @MongoDB\Field(type="string")
     */
    private $username;

    /**
     * @MongoDB\Field(type="string")
     */
    private $password;

    // Getters e setters...
}