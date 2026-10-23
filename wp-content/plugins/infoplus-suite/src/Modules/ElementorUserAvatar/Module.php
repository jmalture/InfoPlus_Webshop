<?php
namespace Infoplus\Suite\Modules\ElementorUserAvatar;

use Infoplus\Suite\Contracts\ModuleInterface;

if (!defined('ABSPATH')) exit;

final class Module implements ModuleInterface
{
    public function slug(): string  { return 'elementor-user-avatar'; }
    public function label(): string { return __('Elementor User Avatar', 'infoplus-suite'); }

    public function register(): void
    {
        // Alternative: Hook into get_avatar to make WordPress use uploaded images
        add_filter('get_avatar_url', [$this, 'customAvatarUrl'], 10, 3);
    }

    /**
     * Filter get_avatar_url to use custom user profile picture
     * This makes it work with Elementor's default "User Profile Picture" dynamic tag
     * 
     * @param string $url The URL of the avatar
     * @param mixed $id_or_email The user ID or email
     * @param array $args Arguments passed to get_avatar_url
     * @return string Modified avatar URL
     */
    public function customAvatarUrl($url, $id_or_email, $args): string
    {
        // Get user ID
        $user_id = null;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif ($id_or_email instanceof \WP_User) {
            $user_id = $id_or_email->ID;
        } elseif ($id_or_email instanceof \WP_Post) {
            $user_id = $id_or_email->post_author;
        }

        if (!$user_id) {
            return $url;
        }

        // Try different meta keys that common user profile picture plugins use
        $possible_meta_keys = [
            'user_profile_picture',           // User Profile Picture plugin
            'infoplus_user_avatar',           // Infoplus custom avatar
            'wp_user_avatar',                 // WP User Avatar plugin
            'basic_user_avatar',              // Simple Local Avatars
            'simple_local_avatar',            // Simple Local Avatars
            'infoplus_user_avatar',           // Our custom field (if we create one)
        ];

        foreach ($possible_meta_keys as $meta_key) {
            $custom_avatar = get_user_meta($user_id, $meta_key, true);
            
            if (!empty($custom_avatar)) {
                // If it's an attachment ID, get the URL
                if (is_numeric($custom_avatar)) {
                    $image_url = wp_get_attachment_url($custom_avatar);
                    if ($image_url) {
                        return $image_url;
                    }
                }
                
                // If it's already a URL
                if (filter_var($custom_avatar, FILTER_VALIDATE_URL)) {
                    return $custom_avatar;
                }
                
                // If it's an array with URL (some plugins store it this way)
                if (is_array($custom_avatar) && isset($custom_avatar['url'])) {
                    return $custom_avatar['url'];
                }
                
                if (is_array($custom_avatar) && isset($custom_avatar['full'])) {
                    return $custom_avatar['full'];
                }
            }
        }

        return $url;
    }
}