<?php
class User extends CI_Model
{
    function __construct()
    {
        $this->load->database();
        parent::__construct();
    }

    function setup($data)
    {
        $this->db->insert('tbl_users', $data);
        return $this->db->insert_id();
    }
}
