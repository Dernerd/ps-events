<?php
/*
Plugin Name: BuddyPress: Meine Veranstaltungen
Description: Fügt Deinen Benutzerprofilen eine Registerkarte "Ereignisse" hinzu.
Plugin URI: https://n3rds.work/piestingtal-source-project/eventsps-das-eventmanagment-fuer-wordpress/
Version: 1.1
AddonType: BuddyPress
Author: DerN3rd
*/

/*
Detail: Zeigt Listen der Benutzer-RSVPs auf den Mitgliederseiten Ihrer Benutzer an. Integriert Statusmeldungen für die BuddyPress Activity
*/ 

class Eab_BuddyPress_MyEvents {
	
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_BuddyPress_MyEvents;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		
		add_action('bp_init', array($this, 'add_bp_profile_entry'));
	}
	
	function show_nags () {
		if (!defined('BP_VERSION')) {
			echo '<div class="error"><p>' .
				__("Du musst BuddyPress installiert und aktiviert haben, damit die Erweiterung <strong>Meine Ereignisse</strong> funktioniert", Eab_EventsHub::TEXT_DOMAIN) .
			'</p></div>';
		}
	}

	private function _check_permissions () {
		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		return current_user_can($post_type->cap->edit_posts);
	}
	
	function add_bp_profile_entry () {
		global $bp;
		bp_core_new_nav_item(array(
			'name' => __('Veranstaltungen', Eab_EventsHub::TEXT_DOMAIN),
			'slug' => 'my-events',
			'show_for_displayed_user' => true,
			'default_subnav_slug' => ($this->_check_permissions() ? 'organized' : 'attending'),
			'screen_function' => '__return_false',
		));
		if ($this->_check_permissions()) {
			bp_core_new_subnav_item(array(
				'name' => __('Organisiert', Eab_EventsHub::TEXT_DOMAIN),
				'slug' => 'organized',
				'parent_url' => $bp->displayed_user->domain . 'my-events' . '/',
				'parent_slug' => 'my-events',
				'screen_function' => array($this, 'bind_bp_organized_page'),
			));
		}
		bp_core_new_subnav_item(array(
			'name' => __('Teilnahme', Eab_EventsHub::TEXT_DOMAIN),
			'slug' => 'attending',
			'parent_url' => $bp->displayed_user->domain . 'my-events' . '/',
			'parent_slug' => 'my-events',
			'screen_function' => array($this, 'bind_bp_attending_page'),
		));
		bp_core_new_subnav_item(array(
			'name' => __('Möglich', Eab_EventsHub::TEXT_DOMAIN),
			'slug' => 'mabe',
			'parent_url' => $bp->displayed_user->domain . 'my-events' . '/',
			'parent_slug' => 'my-events',
			'screen_function' => array($this, 'bind_bp_maybe_page'),
		));
		bp_core_new_subnav_item(array(
			'name' => __('Abgesagt', Eab_EventsHub::TEXT_DOMAIN),
			'slug' => 'not-attending',
			'parent_url' => $bp->displayed_user->domain . 'my-events' . '/',
			'parent_slug' => 'my-events',
			'screen_function' => array($this, 'bind_bp_not_attending_page'),
		));
		do_action('eab-events-my_events-set_up_navigation');
	}
	
	function bind_bp_organized_page () {
		add_action('bp_template_title', array($this, 'show_organized_title'));
		add_action('bp_template_content', array($this, 'show_organized_body'));
		add_action('bp_head', array($this, 'enqueue_dependencies'));
		bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
	}
	function bind_bp_attending_page () {
		add_action('bp_template_title', array($this, 'show_attending_title'));
		add_action('bp_template_content', array($this, 'show_attending_body'));
		add_action('bp_head', array($this, 'enqueue_dependencies'));
		bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
	}
	function bind_bp_maybe_page () {
		add_action('bp_template_title', array($this, 'show_maybe_title'));
		add_action('bp_template_content', array($this, 'show_maybe_body'));
		add_action('bp_head', array($this, 'enqueue_dependencies'));
		bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
	}
	function bind_bp_not_attending_page () {
		add_action('bp_template_title', array($this, 'show_not_attending_title'));
		add_action('bp_template_content', array($this, 'show_not_attending_body'));
		add_action('bp_head', array($this, 'enqueue_dependencies'));
		bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
	}
	
	function enqueue_dependencies () {
		global $bp;
		if ('my-events' != $bp->current_component) return false;
		wp_enqueue_style('eab-bp-my_events', EAB_PLUGIN_URL . 'css/eab-buddypress-my_events.css');
	}
	
	function show_organized_title () {
		echo __('Organisierte Ereignisse', Eab_EventsHub::TEXT_DOMAIN);
	}
	function show_attending_title () {
		echo __('Teilnahme', Eab_EventsHub::TEXT_DOMAIN);
	}
	function show_maybe_title () {
		echo __('Mögliche Teilnahme', Eab_EventsHub::TEXT_DOMAIN);
	}
	function show_not_attending_title () {
		echo __('Abgesagte Teilnahme', Eab_EventsHub::TEXT_DOMAIN);
	}

	function show_organized_body () {
		global $bp;
		echo '<div id="eab-bp-my_events-wrapper">';
		echo '<div class="eab-bp-my_events eab-bp-organized">' . 
			Eab_Template::get_user_organized_events($bp->displayed_user->id) .
		'</div>';
		echo '</div>';
	}
	function show_attending_body () {
		global $bp;
		$premium = $this->_data->get_option('bp-my_events-premium_events');
		if (!empty($premium)) {
			if ('nag' == $premium) add_filter('eab-event-user_events-before_meta', array($this, 'premium_event_rsvp'), 10, 3);
			if ('hide' == $premium) add_filter('eab-event-user_events-exclude_event', array($this, 'exclude_premium_event_rsvp'), 10, 2);
		}
		echo '<div id="eab-bp-my_events-wrapper">';
		echo '<div class="eab-bp-my_events eab-bp-rsvp_yes">' . 
			Eab_Template::get_user_events(Eab_EventModel::BOOKING_YES, $bp->displayed_user->id) .
		'</div>';
		echo '</div>';
	}
	function show_maybe_body () {
		global $bp;
		echo '<div id="eab-bp-my_events-wrapper">';
		echo '<div class="eab-bp-my_events eab-bp-rsvp_maybe">' . 
			Eab_Template::get_user_events(Eab_EventModel::BOOKING_MAYBE, $bp->displayed_user->id) .
		'</div>';
		echo '</div>';
	}
	function show_not_attending_body () {
		global $bp;
		echo '<div id="eab-bp-my_events-wrapper">';
		echo '<div class="eab-bp-my_events eab-bp-rsvp_no">' . 
			Eab_Template::get_user_events(Eab_EventModel::BOOKING_NO, $bp->displayed_user->id) .
		'</div>';
		echo '</div>';
	}

	function premium_event_rsvp ($content, $event, $status) {
		if (!$event->is_premium()) return $content;

		global $bp;
		$user_id = $bp->displayed_user->id;
		if (Eab_EventModel::BOOKING_YES != $status) return $content;
		if ($event->user_paid($user_id)) return $content;
		
		$content .= '<div class="eab-premium_event-unpaid_notice"><b>' . __('Veranstaltung nicht bezahlt', Eab_EventsHub::TEXT_DOMAIN) . '</b></div>';

		return $content;
	}

	function exclude_premium_event_rsvp ($exclude, $event) {
		if ($exclude) return $exclude;

		global $bp;
		$user_id = $bp->displayed_user->id;

		if (!$event->is_premium()) return false;
		return !$event->user_paid($user_id);
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );
		$premium = $this->_data->get_option('bp-my_events-premium_events');
		$options = array(
			'' => __('Mach nichts Besonderes', Eab_EventsHub::TEXT_DOMAIN),
			'hide' => __('Verberge', Eab_EventsHub::TEXT_DOMAIN),
			'nag' => __('Nörgelhinweis anzeigen', Eab_EventsHub::TEXT_DOMAIN),
		);
?>
<div id="eab-settings-my_events" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Meine Ereignisse Einstellungen', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item" style="line-height:1.8em">
	    	<label for="eab_event-bp-my_events-premium_events"><?php _e('Nicht bezahlte Premium-Events mit positiven RSPVs', Eab_EventsHub::TEXT_DOMAIN); ?>:</label>
	    	<?php foreach ($options as $value => $label) { ?>
	    		<br />
				<input type="radio" id="eab_event-bp-my_events-premium_events-<?php echo esc_attr($value); ?>" name="event_default[bp-my_events-premium_events]" value="<?php echo esc_attr($value); ?>" <?php checked($value, $premium); ?> />
	    		<label for="eab_event-bp-my_events-premium_events-<?php echo esc_attr($value); ?>"><?php echo esc_html($label) ?></label>
	    	<?php } ?>
			<span><?php echo $tips->add_tip(__('Umgang mit nicht bezahlten Premium-Ereignissen in der Anzeige der Benutzerereignisliste.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['bp-my_events-premium_events'] = $_POST['event_default']['bp-my_events-premium_events'];
		return $options;
	}
}

Eab_BuddyPress_MyEvents::serve();


class Eab_MyEvents_Shortcodes extends Eab_Codec {

	protected $_shortcodes = array(
		'my_events' => 'eab_my_events',
	);

	public static function serve () {
		$me = new Eab_MyEvents_Shortcodes;
		$me->_register();
	}

	function process_my_events_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
		// Query arguments
			'user' => false, // User ID or keyword
		// Appearance arguments
			'class' => 'eab-my_events',
			'show_titles' => 'yes',
			'sections' => 'organized,yes,maybe,no',
		));

		if (is_numeric($args['user'])) {
			$args['user'] = $this->_arg_to_int($args['user']);
		} else {
			if ('current' == trim($args['user'])) {
				$user = wp_get_current_user();
				$args['user'] = $user->ID;
			} else {
				$args['user'] = false;
			}
		}
		if (empty($args['user'])) return $content;

		$args['sections'] = $this->_arg_to_str_list($args['sections']);
		$args['show_titles'] = $this->_arg_to_bool($args['show_titles']);

		$output = '';

		// Check if the user can organize events
		$post_type = get_post_type_object(Eab_EventModel::POST_TYPE);
		if (in_array('organized', $args['sections']) && user_can($args['user'], $post_type->cap->edit_posts)) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-organized">' . 
				($args['show_titles'] ? '<h4>' . __('Organisierte Veranstaltungen', Eab_EventsHub::TEXT_DOMAIN) . '</h4>' : '') .
				Eab_Template::get_user_organized_events($args['user']) .
			'</div>';
		}

		if (in_array('yes', $args['sections'])) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-rsvp_yes">' . 
				($args['show_titles'] ? '<h4>' . __('Teilnahme', Eab_EventsHub::TEXT_DOMAIN) . '</h4>' : '') .
				Eab_Template::get_user_events(Eab_EventModel::BOOKING_YES, $args['user']) .
			'</div>';
		}
	
		if (in_array('maybe', $args['sections'])) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-rsvp_maybe">' . 
				($args['show_titles'] ? '<h4>' . __('Mögliche Teilnahme', Eab_EventsHub::TEXT_DOMAIN) . '</h4>' : '') .
				Eab_Template::get_user_events(Eab_EventModel::BOOKING_MAYBE, $args['user']) .
			'</div>';
		}
		
		if (in_array('no', $args['sections'])) {
			$output .= '<div class="' . $args['class'] . ' eab-bp-rsvp_no">' . 
				($args['show_titles'] ? '<h4>' . __('Abgesagte Teilnahme', Eab_EventsHub::TEXT_DOMAIN) . '</h4>' : '') .
				Eab_Template::get_user_events(Eab_EventModel::BOOKING_NO, $args['user']) .
			'</div>';
		}

		$output = $output ? $output : $content;

		return $output;
	}

	public function add_my_events_shortcode_help ($help) {
		$help[] = array(
			'title' => __('Mein Veranstaltungsarchiv', Eab_EventsHub::TEXT_DOMAIN),
			'tag' => 'eab_my_events',
			'arguments' => array(
				'user' => array('help' => __('Benutzer-ID oder Schlüsselwort "aktuell".', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:or_integer'),
				'class' => array('help' => __('Wende diese CSS-Klasse an', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string'),
				'show_titles' => array('help' => __('Abschnittstitel anzeigen', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'boolean'),
				'sections' => array('help' => __('Zeigen Sie diese Abschnitte. Mögliche Werte: "Organisiert", "Ja", "Möglich", "Nein".', Eab_EventsHub::TEXT_DOMAIN), 'type' => 'string:list'),
			),
		);
		return $help;
	}
}

Eab_MyEvents_Shortcodes::serve();
