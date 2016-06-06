<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
form ()
insert_edc_registration_details ()
track_status ()
history ()
*/
class Booking extends MY_Controller
{
	function __construct()
	{
		parent::__construct(array('emp','stu'));
		$this->addJS("edc_booking/booking.js");

		$this->load->model('edc_booking/edc_booking_model');
		$this->load->model ('user_model');

		date_default_timezone_set('Asia/Kolkata');
	}

	function auth_is($auth)
	{
		foreach($this->session->userdata('auth') as $a){
			if($a == $auth)
				return;
		}
		$this->session->set_flashdata('flashWarning', 'You do not have access to that page!');
		redirect('home');
	}

	function get_head($dept_id)
	{
		//get the hod or hos of dept
		//if dept is academic then hod otherwise hos for nonacademic
		if($this->edc_booking_model->is_academic($dept_id))	//returns true if academic dept
			return 'hod';
		else return 'hos';
	}

	function form ()
	{
		$data['auth'] = $this->session->userdata('auth')[0];	//either emp or stu		
		$this->drawHeader('Executive Development Center');
		$this->load->view('edc_booking/booking_form', $data);
		$this->drawFooter();
	}

	//receive application details
	function insert_edc_registration_details ()
	{
		$data = array(
				'app_num' => 'EDC'.time(),
			  	'app_date' => date('Y-m-d:H-i-s'), //actually its 19800 for IST
			  	'user_id' => $this->session->userdata('id'),
			  	'purpose'=> $this->input->post('purpose'),
			  	'purpose_of_visit' => $this->input->post('purpose_of_visit'),
			  	'name' => $this->input->post('name'),
			  	'designation' => $this->input->post('designation'),
			  	'no_of_guests' => $this->input->post('no_of_guests'),
			  	'double_AC' => $this->input->post('double_AC'),
			  	'suite_AC' => $this->input->post('suite_AC'),
			  	'boarding_required' => $this->input->post('boarding_required'),
			  	'school_guest' => $this->input->post('school_guest')
		);

		$data['tariff'] = $this->edc_booking_model->get_current_tariff();
		$checkin = $this->input->post('checkin').' '.$this->input->post('checkin_time');
		$data['check_in'] = DateTime::createFromFormat('Y-m-d H:i A', $checkin)->format('Y-m-d H:i:s');
		$checkout = $this->input->post('checkout').' '.$this->input->post('checkout_time');
		$data['check_out'] = DateTime::createFromFormat('Y-m-d H:i A', $checkout)->format('Y-m-d H:i:s');

		if($data['school_guest'] == '1')
		{
			//format file, its filename, filepath, validate (returns upload array)
			$upload = $this->upload_file('approval_letter', $data['app_num']);
			if ($upload)
				$data['file_path'] = $upload['file_name'];

			$data['hod_status'] = '';
			$data['dsw_status'] = '';
			$data['ctk_allotment_status'] = '';
		}

		//for employees -> personal application
		if ($this->session->userdata('auth')[0] == 'emp' && $data['purpose'] == 'Personal') {
			$data['ctk_allotment_status'] = 'Pending';

			$res = $this->user_model->getUsersByDeptAuth('all', 'pce_da5');
			$pce_da5 = '';
			foreach ($res as $row) //assuming only 1 EDC CTK
				$pce_da5 = $row->id;
			$this->notification->notify ($pce_da5, "pce_da5", "EDC Room Allotment Request", "EDC Room Booking Request (Application No. : ".$data['app_num']." ) is Pending for Room Allotment.", "edc_booking/booking_request/notification_handler/".$data['app_num']."/pce_da5", "");
		}

		//for employees -> official application
		if ($this->session->userdata('auth')[0] == 'emp' && $data['purpose'] == 'Official') {
			$data['hod_status'] = 'Pending';

			$_auth = $this->get_head($this->session->userdata('dept_id'));
			$res = $this->user_model->getUsersByDeptAuth($this->session->userdata('dept_id'), $_auth);
			$hod = '';
			foreach ($res as $row)
				$hod = $row->id; //only 1 HOD per dept
			$this->notification->notify ($hod, $_auth, "Approve/Reject Pending Request", "EDC Room Booking Request (Application No. : ".$data['app_num']." ) is Pending for your Approval/Rejection.", "edc_booking/booking_request/notification_handler/".$data['app_num']."/".$_auth, "");
		}

		//for student
		if ($this->session->userdata('auth')[0] == 'stu') {
			$data['dsw_status'] = 'Pending';

			$res = $this->user_model->getUsersByDeptAuth('all', 'dsw');
			$dsw = '';
			foreach ($res as $row) //only 1 DSW
				$dsw = $row->id;
			$this->notification->notify ($dsw, "dsw", "Approve/Reject Pending Request", "EDC Room Booking Request (Application No. : ".$data['app_num']." ) is Pending for your Approval/Rejection.", "edc_booking/booking_request/notification_handler/".$data['app_num']."/dsw", "");
		}

		$this->edc_booking_model->insert_edc_registration_details ($data);

		$this->session->set_flashdata('flashSuccess','Room Allotment request has been successfully sent.');
		redirect('edc_booking/booking/track_status');
	}

	//for pce_da5 other bookings
	function other_bookings_form() {
		$this->drawHeader('Executive Development Center');
		$this->load->view('edc_booking/other_bookings_form');
		$this->drawFooter();
	}

	function insert_other_booking_details() {
		$data = array(
				'app_num' => 'EDC'.time(),
			  	'app_date' => date('Y-m-d:H-i-s'), //actually its 19800 for IST
			  	'user_id' => $this->session->userdata('id'),
			  	'purpose'=> $this->input->post('purpose'),
			  	'purpose_of_visit' => $this->input->post('purpose_of_visit'),
			  	'name' => 'Others',
			  	'designation' => 'pce_da5',
			  	'no_of_guests' => $this->input->post('no_of_guests'),
			  	'double_AC' => $this->input->post('double_AC'),
			  	'suite_AC' => $this->input->post('suite_AC'),
			  	'boarding_required' => $this->input->post('boarding_required'),
			  	'school_guest' => $this->input->post('school_guest')
		);

		$data['tariff'] = $this->edc_booking_model->get_current_tariff();
		$checkin = $this->input->post('checkin').' '.$this->input->post('checkin_time');
		$data['check_in'] = DateTime::createFromFormat('Y-m-d H:i A', $checkin)->format('Y-m-d H:i:s');
		$checkout = $this->input->post('checkout').' '.$this->input->post('checkout_time');
		$data['check_out'] = DateTime::createFromFormat('Y-m-d H:i A', $checkout)->format('Y-m-d H:i:s');

		if($data['school_guest'] == '1')
		{
			//format file, its filename, filepath, validate (returns upload array)
			$upload = $this->upload_file('approval_letter', $data['app_num']);
			if ($upload)
				$data['file_path'] = $upload['file_name'];

			$data['hod_status'] = '';
			$data['dsw_status'] = '';
			$data['ctk_allotment_status'] = '';
		}
		$data['ctk_allotment_status'] = 'Pending';
		$this->notification->notify($this->session->userdata('id'), 'pce_da5', "EDC Room Allotment Request", "EDC Room Booking Request (Application No. : ".$data['app_num']." ) is Pending for Room Allotment", "edc_booking/booking_request/notification_handler/".$data['app_num']."/pce_da5", "");
		$this->edc_booking_model->insert_edc_registration_details ($data);

		$this->session->set_flashdata('flashSuccess','Room Allotment request has been successfully sent.');
		redirect('home');
	}

	function track_status()
	{
		$this->load->model('edc_booking/edc_booking_model');
		$res = $this->edc_booking_model->get_pending_booking_details($this->session->userdata('id'));

		$total_rows = count($res);

		if($total_rows === 0){
			$this->session->set_flashdata('flashError','You don\'t have any application to track.');
			redirect('edc_booking/booking/history');
		}

		$data_array = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array[$sno]=array();
			$j=1;
			$data_array[$sno][$j++] = $row['app_num'];
			$data_array[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array[$sno][$j++] = $row['no_of_guests'];
			$data_array[$sno]['hod_status'] = $row['hod_status'];
			$data_array[$sno]['dsw_status'] = $row['dsw_status'];
			$data_array[$sno]['pce_status'] = $row['pce_status'];
			$sno++;
		}
		$data['data_array'] = $data_array;
		$data['total_rows'] = $total_rows;
		$data ['auth'] = $this->session->userdata('auth')[0]; //sending emp or stu

		$this->drawHeader('Track Booking Status');
 		$this->load->view('edc_booking/booking_track_status', $data);
		$this->drawFooter();
	}

	function history()
	{
		$this->load->model('edc_booking/edc_booking_model');
		$this->load->model('user_model');

		$res = $this->edc_booking_model->get_booking_history ($this->session->userdata('id'), "Approved");
		$total_rows_approved = count($res);
		$data_array_approved = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_approved[$sno]=array();
			$j=1;
			$data_array_approved[$sno][$j++] = $row['app_num'];
			$data_array_approved[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_approved[$sno][$j++] = $row['no_of_guests'];
			foreach($this->edc_booking_model->get_booking_details($row['app_num']) as $status)
				$data_array_approved[$sno][$j++] = array('hod_status' => $status['hod_status'],
															'dsw_status' => $status['dsw_status'],
															'pce_status' => $status['pce_status']);
			$data_array_approved[$sno]['guest_checked_in'] = count($this->edc_booking_model->get_guest_details($row['app_num']));
			$sno++;
		}

		$res = $this->edc_booking_model->get_booking_history ($this->session->userdata('id'), "Rejected");
		$total_rows_rejected = count($res);
		$data_array_rejected = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_rejected[$sno]=array();
			$j=1;
			$data_array_rejected[$sno][$j++] = $row['app_num'];
			$data_array_rejected[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_rejected[$sno][$j++] = $row['no_of_guests'];
			$data_array_rejected[$sno][$j++] = "";
			if ($row['hod_status'] == "Rejected")
			{
				if($this->sah_booking_model->is_academic($this->session->userdata('dept_id')))
					$data_array_rejected[$sno][4] = "Head of Department";
				else $data_array_rejected[$sno][4] = "Head of Section";
			}
			else if($row['dsw_status'] == "Rejected")
				$data_array_rejected[$sno][4] = "Dean of Students Welfare";
			else $data_array_rejected[$sno][4] = "PCE";

			$sno++;
		}

		$res = $this->edc_booking_model->get_booking_history ($this->session->userdata('id'), "Cancelled");
		$total_rows_cancelled = count($res);
		$data_array_cancelled = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_cancelled[$sno]=array();
			$j=1;
			$data_array_cancelled[$sno][$j++] = $row['app_num'];
			$data_array_cancelled[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_cancelled[$sno][$j++] = $row['no_of_guests'];
			$data_array_cancelled[$sno][$j++] = $row['deny_reason'];
			$data_array_cancelled[$sno][$j++] = $row['cancellation_date'];
			$sno++;
		}

		$data['data_array_approved'] = $data_array_approved;
		$data['total_rows_approved'] = $total_rows_approved;
		$data['data_array_rejected'] = $data_array_rejected;
		$data['total_rows_rejected'] = $total_rows_rejected;
		$data['data_array_cancelled'] = $data_array_cancelled;
		$data['total_rows_cancelled'] = $total_rows_cancelled;

		$data ['auth'] = $this->session->userdata('auth')[0];

		$this->drawHeader('Executive Development Center');
		$this->load->view('edc_booking/booking_history',$data);
		$this->drawFooter();
	}

	private function upload_file($name ='', $app_num='')
	{
		$config['upload_path'] = 'assets/files/edc_booking/'.$this->session->userdata('id').'/';
		$config['allowed_types'] = 'jpg|jpeg|png';
		$config['max_size']  = '2048';

			if(isset($_FILES[$name]['name']))
        	{
                if($_FILES[$name]['name'] == "")
            		$filename = "";
                else
				{
                    $filename=$this->security->sanitize_filename(strtolower($_FILES[$name]['name']));
                    $ext =  strrchr( $filename, '.' ); // Get the extension from the filename.
                    $filename='FILE_'.$app_num.$ext;
                }
	        }
	        else
	        {
	        	$this->session->set_flashdata('flashError','ERROR: File Name not set.');
	        	redirect('edc_booking/booking/form');
				return FALSE;
	        }

			$config['file_name'] = $filename;

			if(!is_dir($config['upload_path']))	//create the folder if it not already exists
			{
				mkdir($config['upload_path'],0777,TRUE);
			}

			$this->load->library('upload', $config);

			if ( ! $this->upload->do_multi_upload($name))		//do_multi_upload is back compatible with do_upload
			{
				$this->session->set_flashdata('flashError',$this->upload->display_errors('',''));
				redirect('edc_booking/booking/form');
				return FALSE;
			}
			else
			{
				$upload_data = $this->upload->data();
				return $upload_data;
			}
	}
}
