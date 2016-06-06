
<?php

if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
pce_da5_action ($app_num)
get_room_plans ($building, $check_in, $check_out)
insert_edc_allotment ($app_num)
*/
class Room_allotment extends MY_Controller

{

	// Loads the required models and allows access to its functions to people with auth=pce_da5,pce .

	function __construct()
	{
		parent::__construct(array(
			'pce_da5',
			'pce'
		));
		$this->addJS("edc_booking/booking.js");
		$this->load->model('edc_booking/edc_allotment_model');
		$this->load->model('edc_booking/edc_booking_model');
		$this->initialize_buildings();
	}

	// Initialize building values i.e. old->0 and extension->1 .

	function initialize_buildings()
	{
		$initializer_id = array(
			'old' => 0,
			'extension' => 1
		);
		$this->initialize_building('old', $initializer_id);
		$this->initialize_building('extension', $initializer_id);
	}

	// Calls to model function to set the values of buildings .

	function initialize_building($building = '', $initializer_id)
	{
		if ($this->edc_allotment_model->no_of_rooms($building) === 0)
		{
			$this->edc_allotment_model->initialize_building($building, $initializer_id[$building]);
		}
	}

	// Checks that user with only allowed auth_id accesses the page .

	function auth_is($auth)
	{
		foreach($this->session->userdata('auth') as $a)
		{
			if ($a == $auth) return;
		}

		$this->session->set_flashdata('flashWarning', 'You do not have access to that page!');
		redirect('home');
	}

	// Function allows caretaker of EDC to take action regarding an application i.e. allot rooms if application is not yet cancelled .
	// Application Number is passed as an argument .

	function pce_da5_action($app_num)
	{
		$this->auth_is('pce_da5');
		$res = $this->edc_booking_model->get_booking_details($app_num);

		// Check if the application status is cancelled if so redirect to list of applications page .

		if ($res[0]['hod_status'] === 'Cancel' || $res[0]['hod_status'] === 'Cancelled' || $res[0]['dsw_status'] === 'Cancel' || $res[0]['dsw_status'] === 'Cancelled' || $res[0]['pce_status'] === 'Cancelled')
		{
			$this->session->set_flashdata('flashError', 'Cannot allot room! Applicant has cancelled booking request.');
			redirect('edc_booking/booking_request/pce_da5_app_list');
		}

		$data = array();
		foreach($res as $row)
		{
			$data = array(
				'check_in' => $row['check_in'],
				'check_out' => $row['check_out'],
				'double_AC' => $row['double_AC'],
				'suite_AC' => $row['suite_AC'],
				'pce_status' => $row['pce_status']
			);
		}

		$data['app_num'] = $app_num;

		// Load the view to allot rooms if application status is not cancelled .

		$this->drawHeader("Room Allotment");
		$this->load->view('edc_booking/edc_allotment_view', $data);
		$this->drawFooter();
	}

	// Function gets the appropriate room plans which are free for allotment .

	function get_room_plans($building, $check_in = '', $check_out = '')
	{

		// If the building has no room load the given view .

		if ($this->edc_allotment_model->no_of_rooms($building) === 1)
		{
			$this->load->view('edc_booking/no_room_data.php');
		}
		else
		{

			// Get room_id of unavailable rooms .

			$result_uavail_rooms = $this->edc_allotment_model->check_unavail($check_in, $check_out);

			// Get list of rooms grouped by floors .

			$floor_array = $this->edc_allotment_model->get_floors($building);
			$flr = 1;
			foreach($floor_array as $floor)
			{
				$temp_query = $this->edc_allotment_model->get_rooms($building, $floor['floor']);
				$result_floor_wise[$flr][0] = $temp_query;
				$result_floor_wise[$flr++][1] = $floor['floor'];
			}

			$data_array = array();
			$i = 0;
			foreach($result_floor_wise as $floor)
			{
				$sno = 1;
				$data_array[$i][0] = $floor[1];
				foreach($floor[0] as $row)
				{
					$flag = 0;
					foreach($result_uavail_rooms as $room_unavailable)
					{
						if ($row['id'] == $room_unavailable['room_id']) $flag = 1;
					}

					$data_array[$i][$sno][0] = $row['id'];
					$data_array[$i][$sno][1] = $row['room_no'];
					$data_array[$i][$sno][2] = $row['room_type'];
					if ($flag == 0)
					{

						// If room available for booking .

						$data_array[$i][$sno][3] = 1;
					}
					else
					{

						// If room already booked .

						$data_array[$i][$sno][3] = 0;
					}

					// Stores status of room if it is blocked by caretaker or not .

					$data_array[$i][$sno][4] = $row['blocked'];

					// Stores remark for block .

					$data_array[$i][$sno++][5] = $row['remark'];
				}

				$i++;
			}

			$data['floor_room_array'] = $data_array;
			$data['room_array'] = $this->edc_allotment_model->get_room_types();

			// Load the view to allot the available rooms .

			$this->load->view('edc_booking/edc_rooms', $data);
		}
	}

	// Function inserts the allotment details into database .

	function insert_edc_allotment($app_num)
	{
		$this->auth_is('pce_da5');
		$booking_details = $this->edc_booking_model->get_booking_details($app_num);
		foreach($booking_details as $b_detail)
		{

			// If room is already alloted then flash the warning and redirect to applications page .

			if ($b_detail['ctk_allotment_status'] === 'Approved')
			{
				$this->session->set_flashdata('flashError', 'Invalid attempt to allot room. Room Allotment has already been done.');
				redirect('edc_booking/booking_request/pce_da5_app_list');
			}

			// If application was cancelled by any of the attesting authorities then also flash warning and redirect to applications page .

			else
			if ($b_detail['hod_status'] === 'Cancel' || $b_detail['hod_status'] === 'Cancelled' || $b_detail['dsw_status'] === 'Cancel' || $b_detail['dsw_status'] === 'Cancelled' || $b_detail['pce_status'] === 'Cancelled')
			{
				$this->session->set_flashdata('flashError', 'Cannot allot room! Applicant has cancelled booking request.');
				redirect('edc_booking/booking_request/pce_da5_app_list');
			}
		}

		// Stores count of the no. of respective rooms selected by caretaker .

		$double_bedded_ac = $this->input->post('checkbox_double_bedded_ac');
		$ac_suite = $this->input->post('checkbox_ac_suite');
		if (gettype($double_bedded_ac) == 'array' && gettype($ac_suite) == 'array') $room_list = array_merge($double_bedded_ac, $ac_suite);
		else
		if (gettype($double_bedded_ac) == 'array') $room_list = $double_bedded_ac;
		else $room_list = $ac_suite;

		// Sets the caretaker status to approved for given application number .

		$this->edc_allotment_model->set_ctk_status("Approved", $app_num);

		// Inserts room_id corresponding to app_num in the database . Hence mapping room to its applicant .

		foreach($room_list as $room)
		{
			$input_data = array(
				'app_num' => $app_num,
				'room_id' => $room,
			);
			$this->edc_allotment_model->insert_booking_details($input_data);
		}

		$this->load->model('user_model');
		$res = $this->user_model->getUsersByDeptAuth('all', 'pce');
		$pce = '';

		// Sends the notification to PCE after caretaker has taken action .

		foreach($res as $row)
		{
			$pce = $row->id;
			$this->notification->notify($pce, "pce", "Approve/Reject Pending Request", "EDC Room Booking Request (Application No. : " . $app_num . " ) is Pending for your approval.", "edc_booking/booking_request/details/" . $app_num . "/pce", "");
		}

		// Flash different alerts for when rooms unavailable and when successful allotment .

		if ($this->input->post("submit") == "Rooms Unavailable") 
			$this->session->set_flashdata('flashSuccess', 'Rooms were not allotted due to unavailability');
		else 
			$this->session->set_flashdata('flashSuccess', 'Room Allotment has been done successfully.');
		redirect('edc_booking/booking_request/pce_da5_app_list');
	}
}




