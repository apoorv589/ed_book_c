<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
room_management()
room_planning($building)
room_status($room_id, $auth)
building_status($auth)
load_building_status($building, $auth)
add_form($building, $floor, $type)
add_rooms()
remove_rooms()
block_rooms()
unblock_rooms()
*/
class Management extends MY_Controller
{
	// Loads the required models and allows access to its functions to people with auth=pce_da5,pce .
	function __construct()
	{
		parent::__construct(array('pce_da5', 'pce'));

		$this->load->model ('edc_booking/edc_allotment_model');
		$this->load->model ('edc_booking/edc_booking_model');

		$this->initialize_buildings();
	}
// Initialize building values i.e. old->0 and extension->1 .
	function initialize_buildings() {
		$initializer_id = array('old' => 0,
								'extension' => 1
		);
		$this->initialize_building('old', $initializer_id);
		$this->initialize_building('extension', $initializer_id);
	}
	// Calls to model function to set the values of buildings .
	function initialize_building($building = '', $initializer_id) {
		if($this->edc_allotment_model->no_of_rooms($building) === 0)
			$this->edc_allotment_model->initialize_building($building, $initializer_id[$building]);
	}
	// Checks that user with only allowed auth_id accesses the page .
	function auth_is($auth)
	{
		foreach($this->session->userdata('auth') as $a){
			if($a == $auth)
				return;
		}
		$this->session->set_flashdata('flashWarning', 'You do not have access to that page!');
		redirect('home');
	}
	//Function which loads the view to view and manage the rooms present .
	function room_management()
	{
		$this->auth_is('pce_da5');
		$this->drawHeader('Executive Development Center');
		$this->load->view('edc_booking/edc_room_planning');
		$this->drawFooter();
	}
	//Function to retrieve the status of rooms from the database .
	function room_planning($building)
	{
		$this->auth_is('pce_da5');

		$result_uavail_rooms = $this->edc_allotment_model->get_booked_rooms(date('Y-m-d H:i:s'));
		$floor_array = $this->edc_allotment_model->get_floors($building);

		$flr = 1;
		//Get the rooms floor wise .
		foreach($floor_array as $floor)
		{
			$temp_query = $this->edc_allotment_model->get_rooms($building,$floor['floor']);
			$result_floor_wise[$flr][0] = $temp_query;
			$result_floor_wise[$flr++][1] = $floor['floor'];
		}

		$data_array = array();
		$i = 0;
		//Get the status of rooms and set corresponding variables floorwise .
		foreach($result_floor_wise as $floor)
		{
			$sno=1;
			$data_array[$i][0] = $floor[1];
			foreach($floor[0] as $row)
			{
				$flag=0;
				foreach($result_uavail_rooms as $room_unavailable)
				{
					if($row['id']==$room_unavailable['room_id'])
						$flag = 1;
				}
				$data_array[$i][$sno][0] = $row['id'];
				$data_array[$i][$sno][1] = $row['room_no'];
				$data_array[$i][$sno][2] = $row['room_type'];
				//If the room is available .
				if($flag==0)
					$data_array[$i][$sno][3] = 1;
				//If the room is already allotted .
				else
					$data_array[$i][$sno][3] = 0;
				$data_array[$i][$sno][4] = $row['blocked'];
				$data_array[$i][$sno++][5] = $row['remark'];
			}
			$i++;
		}

		$data['building'] = $building;
		$data['floor_room_array'] = $data_array;
		//Creates two sections for room types
		$data['room_array'] = $this->edc_allotment_model->get_room_types(); 
		$this->load->view('edc_booking/room_plans',$data);
	}

	function room_status($room_id, $auth) {
		
		
		$data['auth'] = $auth;
		//Gets any application which has check in earlier than current date and has not checked out .
		$checked_app = $this->edc_allotment_model->get_checked_app($room_id);
		

		//Get all bookings for the current room later in future .
		
		

		$room_bookings = $this->edc_allotment_model->get_future_room_bookings($room_id);
	
		if($checked_app)
			$data['checked_app'] = $this->edc_booking_model->get_booking_details($checked_app)[0];
		else $data['checked_app'] = '';
		/*echo "L";
		echo $checked_app;
		echo "F";
		*/$i = 0;		
		//Store the details of future bookings of a room in new array .
		
		foreach($room_bookings as $booking) {
			$data['room_bookings'][$i]['app_num'] = $booking['app_num'];
			$data['room_bookings'][$i++]['name'] = $this->edc_booking_model->get_booking_details($booking['app_num'])[0]['name'];
		}
		
		$this->load->view('edc_booking/room_status', $data);
	}

	function building_status($auth) {
		$this->addJS('edc_booking/room_availability.js');
		$data['auth'] = $auth;
		
		$this->drawHeader('Executive Development Center');
		$this->load->view('edc_booking/building_status', $data);
		
		
		$this->drawFooter();
	}

	function load_building_status($building, $auth) {
		//fetch all room data with current room holders, booked rooms
		//make all rooms a link which will show the application to which room is allotted
//$this->addJS('edc_booking/room_availability.js');	
		$data['auth'] = $auth;
		$result_uavail_rooms = $this->edc_allotment_model->get_booked_rooms(date('Y-m-d H:i:s'));
		$checked_in_rooms = $this->edc_allotment_model->get_checked_rooms();

		if($this->edc_allotment_model->no_of_rooms($building) <= 1)
			$this->load->view('edc_booking/no_room_data');
		else {
			$floor_array = $this->edc_allotment_model->get_floors($building);

			$flr = 1;
			foreach($floor_array as $floor)
			{
				$temp_query = $this->edc_allotment_model->get_rooms($building,$floor['floor']);
				$result_floor_wise[$flr][0] = $temp_query;
				$result_floor_wise[$flr++][1] = $floor['floor'];
			}

			$data_array = array();
			$i = 0;
			foreach($result_floor_wise as $floor) {
				$sno=1;
				$data_array[$i][0] = $floor[1];
				foreach($floor[0] as $row)
				{
					$flag=0; //free
					foreach($result_uavail_rooms as $room_unavailable) //this can be optimized
					{
						if($row['id'] === $room_unavailable['room_id'])
							$flag = 1; //booked
					}
					foreach($checked_in_rooms as $c_room) {
						if($row['id'] === $c_room['room_id'])
							$flag = 2; //checked
					}
					$data_array[$i][$sno][0] = $row['id'];
					$data_array[$i][$sno][1] = $row['room_no'];
					$data_array[$i][$sno][2] = $row['room_type'];
					$data_array[$i][$sno][3] = $flag;
					$data_array[$i][$sno][4] = $row['blocked'];
					$data_array[$i][$sno++][5] = $row['remark'];
				}
				$i++;
			}

			$data['building'] = $building;
			$data['floor_room_array'] = $data_array;
			$data['room_array'] = $this->edc_allotment_model->get_room_types(); //creates two sections for room types
			$this->load->view('edc_booking/building_view', $data);
		}
	}

	function add_form($building, $floor, $type)
	{
		$this->auth_is('pce_da5');
		$data = array(
			'building' => $building,
			'floor' => $floor,
			'type' => $type
		);

		$this->drawHeader('Executive Development Center');
		$this->load->view('edc_booking/add_room', $data);
		$this->drawFooter();
	}

	function add_rooms()
	{
		$data = array(
			'room_no' => $this->input->post('room_no'),
			'building' => strtolower($this->input->post('building')),
			'floor' => strtolower(trim($this->input->post('floor'))),
			'room_type' => $this->input->post('type'),
			'remark' => $this->input->post('remark')
		);

		$this->edc_allotment_model->add_rooms($data);
		redirect('edc_booking/management/room_management');
	}

	function remove_rooms()
	{
		$rooms = $this->input->post('checkbox_rooms');
		$this->edc_allotment_model->remove_rooms($rooms);
		redirect('edc_booking/management/room_management');
	}

	function block_rooms()
	{
		$rooms = $this->input->post('checkbox_rooms');
		$remark = $this->input->post('remark');
		$this->edc_allotment_model->block_rooms($rooms, $remark);
		redirect('edc_booking/management/room_management');
	}

	function unblock_rooms()
	{
		$rooms = $this->input->post('checkbox_rooms');
		$this->edc_allotment_model->unblock_rooms($rooms);
		redirect('edc_booking/management/room_management');
	}
}
