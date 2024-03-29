<?php
class Store extends CI_Controller

{
	public

	function index()
	{
		/*
		$search_load = file_get_contents("https://search.ob1.io/search/listings?q=*&network=mainnet&p=0&ps=64&nsfw=false&acceptedCurrencies=BTC");
		$search_results_json = json_decode($search_load);
		$results = $search_results_json->results->results;
		$result_count = $search_results_json->results->total;
		$data = array('listings' => $results, 'total' => $result_count, 'q'=> '');
		$this->load->view('header');
		$this->load->view('discover', $data);
		*/
	}

	public

	function listing($peerID, $slug)
	{				
		$this->load->driver('cache', array(
			'adapter' => 'apc',
			'backup' => 'file'
		));
		$profile = get_profile($peerID);
		
		if(!isset($profile->name)) {
			$this->load->view('header', array(
				'page_title' => 'OpenBazaar - Error'
			));
			$this->load->view('error_page', array('error'=>'🤔 The store is unreachable. Try again later.'));
			$this->load->view('footer');
			return;
		}
		
		$listing = get_listing($peerID, $slug); 
		
		if(!$listing || check_if_listing_blocked($peerID, $slug)) {
			$this->load->view('header', array(
				'page_title' => 'Listing Not Found - ' . $profile->name . ' - ',
				'body_class' => 'user-listing'
			));

			echo '<div style="margin:20px;">Could not find this listing on the network.</div>';			
			
			$this->load->view('footer');
			
			return;
		}
		
		// Check if listing has free shipping for this user
		$free_shipping = false;
		$shipping_options = $listing->listing->shippingOptions;
		foreach($shipping_options as $shipping_option) {
			foreach($shipping_option->services as $service) {
				if(!isset($service->price)) {
					$free_shipping = true;
				}
			}
		}
		
		$verified_mods = json_decode(loadFile("https://search.ob1.io/verified_moderators"));
		$verified = false;
		
		foreach($listing->listing->moderators as $mod) {
			foreach($verified_mods->moderators as $vermod) {
				if($mod == $vermod->peerID) {
					$verified = true;
					break;
				}
			}
			if($verified) {
				break;
			}
		}
		
		$listing->listing->has_verified_mod = $verified;
		
		$all_listings = get_listings($peerID);	// To display in the more listings by... section		
		$listing_count = count($all_listings);				
		
		shuffle($all_listings);
		$rating = 0;
		$rating_total = 0;
		$rating_count = 0;
		$listing_ratings = array();

		try {
			$ratings_load = @loadFile("http://134.209.23.223:4002/ob/ratings/" . $peerID."?usecache=true");
			if ($ratings_load !== FALSE) {
				$ratings = json_decode($ratings_load);

				foreach($ratings->ratings as $r) {
				    $rating_load = @loadFile("http://134.209.23.223:4002/ob/rating/" . $peerID. "/" . $r . "?usecache=true");
                    if ($rating_load !== FALSE) {
                        $rating_load = json_decode($rating_load);

                        if ($rating_load->ratingData->vendorSig->metadata->listingSlug == $listing->listing->slug) {
                            $rating_total += $rating_load->ratingData->overall;
                            $rating_count++;
                            array_push($listing_ratings, $r);
                        }
                    }
				}
				if($rating_count > 0) {
				    $rating = $rating_total / $rating_count;
				}
			}
		}

		catch(Exception $e) {
		}

		// Grab ratings data files

		$reviews = array();
		foreach($listing_ratings as $r) {
			$review_load = @loadFile("http://134.209.23.223:4002/ipfs/" . $r);
			$review_json = json_decode($review_load);
			array_push($reviews, $review_json);
		}

		// Check for special cryptolisting type

		$is_crypto_listing = ($listing->listing->metadata->contractType == "CRYPTOCURRENCY") ? true : false;
		$data = array(
			'crypto_listing' => $is_crypto_listing,
			'profile' => $profile,
			'listing' => $listing->listing,
			'slug' => $listing->listing->slug,
			'rating' => $rating,
			'ratings' => $rating_count,
			'reviews' => $reviews,
			'all_listings' => $all_listings,
			'listing_count' => $listing_count,
			'free_shipping' => $free_shipping,
			'has_verified_mod' => $verified
		);

		$listing_image_hash = (isset($listing->listing->item->images)) ? $listing->listing->item->images[0]->medium : '';

		$this->load->view('header', array(
			'page_title' => $listing->listing->item->title . ' - ' . $profile->name . ' - ',
			'body_class' => 'user-listing',
			'page_description' => strip_tags($listing->listing->item->description),
			'page_image' => "http://134.209.23.223:4002/ob/images/".$listing_image_hash
		));
		$this->load->view('store_listing', $data);
		$this->load->view('footer');
	}

	public

	function listings($peerID, $category = "All")
	{
		$listings = array();
		$categories = array();
		
		$profile = get_profile($peerID);

		if($profile == "") {
		    show_404();
		}

		$header_image = isset($profile->headerHashes);
		$listings = get_listings($peerID);
		
		$verified_mods = get_verified_mods();		
		$verified = false;
				
		if (!empty($listings)) {
			foreach($listings as $listing) {
				
				if(check_if_listing_blocked($peerID, $listing->slug)) {
					if (($key = array_search($listing, $listings)) !== false) {
					    unset($listings[$key]);
					}
				}
				
				// Populate categories array for the storefront
				if($listing->categories) {
					foreach($listing->categories as $category) {
						array_push($categories, $category);
					}
				}
				
				if(isset($listing->moderators)) {
					foreach($listing->moderators as $mod) {
						if(in_array($mod, $verified_mods)) {
							$verified = true;
							break;
						}
					}
				}
				
			}
		}

		$categories = array_unique($categories);
		$category = "All";				
		
		$countries = file_get_contents(asset_url().'js/countries.json');
    	$countries = json_decode($countries, true);
		
		$data = array(
			'countries' => $countries,
			'category' => $category,
			'profile' => $profile,
			'header_image' => $header_image,
			'listings' => $listings,
			'categories' => $categories,
			'verified_mod' => $verified
		);
		
		$image_hash = ($header_image) ? (isset($profile->headerHashes)) ? $profile->headerHashes->large : '' : "";
		
		$this->load->view('header', array(
			'page_title' => $profile->name . ' - Store - ',
			'body_class' => 'user-store',
			'page_description' => htmlentities(addslashes($profile->shortDescription)),
			'page_image' => "http://134.209.23.223:4002/ob/images/".$image_hash
		));
		$this->load->view('store_meta', $data);
		$this->load->view('store_listings', $data);
		$this->load->view('footer');
	}

	public

	function home($peerID)
	{
		$this->load->driver('cache', array(
			'adapter' => 'apc',
			'backup' => 'file'
		));
		$profile = get_profile($peerID);
		$header_image = isset($profile->headerHashes);
		
		// Get profile visibility info
		$db = $this->load->database('stats', TRUE);
		$sql = "SELECT * FROM nodes WHERE guid = ?";
        $result = $db->query($sql, array($peerID));		        	        
        $results = $result->result_array();		
		
		$data = array(
			'body_class' => 'home',
			'profile' => $profile,
			'header_image' => $header_image,
			'last_seen' => $results[0]['last_seen']
		);
		
		$image_hash = ($header_image) ? (isset($profile->headerHashes)) ? $profile->headerHashes->large : '' : "";
		
		$this->load->view('header', array(
			'body_class' => 'user-home',
			'page_title' => $profile->name . ' - About - ',
			'page_description' => htmlentities(addslashes($profile->shortDescription)),
			'page_image' => "http://134.209.23.223:4002/ob/images/".$image_hash
		));
		$this->load->view('store_meta', $data);
		$this->load->view('store_home', $data);
		$this->load->view('footer');
	}

	public

	function followers($peerID)
	{
		$this->load->driver('cache', array(
			'adapter' => 'apc',
			'backup' => 'file'
		));
		$profile = get_profile($peerID);
		$header_image = isset($profile->headerHashes);
		$followers_load = loadFile("http://134.209.23.223:4002/ob/followers/" . $peerID."?usecache=true");
		$followers = json_decode($followers_load);
		$data = array(
			'body_class' => 'followers',
			'profile' => $profile,
			'header_image' => $header_image,
			'followers' => $followers
		);
		
				
		$this->load->view('header', array(
			'body_class' => 'user-followers',
			'page_title' => $profile->name . ' - Followers - ',
			'page_description' => htmlentities(addslashes($profile->shortDescription)),
			'page_image' => "http://134.209.23.223:4002/ob/images/".$image_hash
		));
		$this->load->view('store_meta', $data);
		$this->load->view('store_followers', $data);
		$this->load->view('footer');
	}

	public

	function following($peerID)
	{
		$this->load->driver('cache', array(
			'adapter' => 'apc',
			'backup' => 'file'
		));
		$profile = get_profile($peerID);
		
		$header_image = isset($profile->headerHashes);
		$followers_load = loadFile("http://134.209.23.223:4002/ob/following/" . $peerID."?usecache=true");
		$followers = json_decode($followers_load);
		$data = array(
			'body_class' => 'following',
			'profile' => $profile,
			'header_image' => $header_image,
			'followers' => $followers
		);
		
		$image_hash = ($header_image) ? (isset($profile->headerHashes)) ? $profile->headerHashes->large : '' : "";
		
		$this->load->view('header', array(
			'body_class' => 'user-following',
			'page_title' => $profile->name . ' - Following - ',
			'page_description' => htmlentities(addslashes($profile->shortDescription)),
			'page_image' => "http://134.209.23.223:4002/ob/images/".$image_hash
		));
		$this->load->view('store_meta', $data);
		$this->load->view('store_following', $data);
		$this->load->view('footer');
	}

	public

	function card($listingID)
	{
	}

	public

    function widget()
    {
        $data = array(
            'body_class' => 'widget',
            'peerID' => 'QmcUDmZK8PsPYWw5FRHKNZFjszm2K6e68BQSTpnJYUsML7'
        );

        $this->load->view('header', $data);
        $this->load->view('store_widget', $data);
        $this->load->view('footer');
    }

    function store_widget($peerID)
    {
        $data = array(
            'body_class' => 'widget',
            'peerID' => $peerID
        );

        $this->load->view('header', $data);
        $this->load->view('store_widget', $data);
        $this->load->view('footer');
    }

    function button_code($peerID) {
        $data = array(
            'peerID'=>$peerID
        );
        $this->load->view('store_button_code', $data);
    }

    function widget_code($peerID) {
        $profile = get_profile($peerID);

        if($profile == "" || isset($profile->reason)) {
            $peerID = "QmcUDmZK8PsPYWw5FRHKNZFjszm2K6e68BQSTpnJYUsML7";
            $profile = get_profile($peerID);
        }     
        
        $header_image = isset($profile->headerHashes);
        $listings = get_listings($peerID);
        $listings = array_slice($listings, 0, 10);

        $verified_mods = get_verified_mods();
        $verified = false;

        if (!empty($listings)) {
            foreach($listings as $listing) {

                if(isset($listing->moderators)) {
                    foreach($listing->moderators as $mod) {
                        if(in_array($mod, $verified_mods)) {
                            $verified = true;
                            break;
                        }
                    }
                }

            }
        }

        $data = array(
            'peerID'=>$peerID,
            'profile'=>$profile,
            'header_image'=>$header_image,
            'listings'=>$listings,
            'verified_mod' => $verified
        );
        $this->load->view('store_widget_code', $data);
    }

}
