<?php
defined('BASEPATH') or exit('No direct script access allowed');

class GuestController extends CI_Controller
{
    private $vendorid;
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('utilities_helper');
        $this->vendorid = isset($_SERVER['HTTP_VENDORID']) ? $_SERVER['HTTP_VENDORID'] : '';
        $this->load->model('User');
    }

    public function index()
    {
        $this->load->view('guest/index');
    }

    public function register()
    {
        if (empty($this->vendorid)) {
            utilities_helper::dieJsonError('Registration can be procces under any Kitchen only, Please provide kitchen Id.');
        }
    }


    public function getForm()
    {
        $this->load->database();
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');
        $this->form_validation->set_rules('user_name', 'Username', 'callback_username_check');
        $this->form_validation->set_rules('user_password', 'Password', 'required');
        $this->form_validation->set_rules('passconf', 'Password Confirmation', 'required');
        $this->form_validation->set_rules('user_email', 'Email', 'required|is_unique[tbl_users.user_email]');
        if ($this->form_validation->run() == FALSE) {
            //utilities_helper::dieJsonError(validation_errors());
			$this->load->view('guest/register-form');
        } else {
            $post = $this->input->post();
            unset($post['passconf']);
            $userid = $this->User->setup($post);
            if (false == $userid) {
                utilities_helper::dieJsonError('Registration failded, please contact admin');
            }
            utilities_helper::dieJsonSuccess('Registration Completed sucessfully.');
        }
    }

    public function username_check($str)
    {
        if ($str == 'test') {
            $this->form_validation->set_message('username_check', 'The {field} field can not be the word "test"');
            return FALSE;
        } else {
            return TRUE;
        }
    }
}
