<?php

class UMM_Loader {

    public function run() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once UMM_PATH . 'includes/class-umm-admin.php';
        require_once UMM_PATH . 'includes/class-umm-rewards.php';
        require_once UMM_PATH . 'includes/class-umm-withdrawal.php';
        require_once UMM_PATH . 'includes/class-umm-referrals.php';
    }

    private function define_admin_hooks() {
        $plugin_admin = new UMM_Admin();
    }

    private function define_public_hooks() {
        $plugin_rewards = new UMM_Rewards();
        $plugin_withdrawal = new UMM_Withdrawal();
        $plugin_referrals = new UMM_Referrals();
    }
}
