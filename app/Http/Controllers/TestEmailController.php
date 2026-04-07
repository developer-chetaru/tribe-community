<?php

namespace App\Http\Controllers;

use App\Mail\WeeklySummaryMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TestEmailController extends Controller
{
    public function sendTestEmail()
    {
        $user = User::find(332);

        if (!$user) {
            return "User ID 332 not found.";
        }

        $weekLabel = "Week 48 (Nov 20 - Nov 26)";
        $summaryText = "• You were positive 4/7 days.\n• Your highest mood score was 8/10.\n• Keep it up!";

        Mail::to($user->email)->send(
            new WeeklySummaryMail($user, $weekLabel, $summaryText)
        );

        return "Test email sent to: " . $user->email;
    }
}
