<?php
namespace App\Services;

use App\Models\HptmLearningChecklist;
use App\Models\User;

class EngagementService
{
    protected $reportService;

   	/**
     * EngagementService constructor.
     *
     * @param \App\Services\EngagementReportService $reportService Service to fetch engagement reports
     */
    public function __construct(\App\Services\EngagementReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Calculate the HPTM score for a given user.
     *
     * @param \App\Models\User $user
     * @return float HPTM score (scaled and rounded)
     */
    public function calculateHptmScore(User $user)
    {
        $totalScore = HptmLearningChecklist::with('outputType') // eager load
            ->get()
            ->sum(function ($item) {
                return optional($item->outputType)->score ?? 0;
            });

        $hptmScore = $user->hptmScore + $user->hptmEvaluationScore;

        return ($totalScore + 400) > 0
        ? round(($hptmScore / ($totalScore + 400)) * 1000, 2)
        : 0;
    }

     /**
     * Calculate total engagement score for a given user.
     *
     * @param \App\Models\User $user
     * @return float Total engagement score including EI and HPTM scores
     */
    public function calculateTotalEngagementScore(User $user)
    {
        $eiScore   = $user->EIScore + 250; // increase EI
        $hptmScore = $this->calculateHptmScore($user);

        return $eiScore + $hptmScore;
    }

   /**
    * Get the user's engagement index for the last day.
    *
    * @param array $userDataArr Array containing user data like 'orgId', 'userId', 'HI_include_saturday', 'HI_include_sunday'
    * @param string|false $date Optional date to calculate engagement for
    * @return float Engagement index (0-100)
    */
   public function getUserEngagementIndexForLastDay(array $userDataArr, $date = false)
    {
        $orgId               = $userDataArr['orgId'];
        $userId              = $userDataArr['userId'];
        $HI_include_saturday = $userDataArr['HI_include_saturday'];
        $HI_include_sunday   = $userDataArr['HI_include_sunday'];

        $dotComplete              = '0';
        $thumbsupComplete         = '0';
        $happyIndexComplete       = '0';
        $teamRoleComplete         = '0';
        $personalityTypeComplete  = '0';
        $cultureStructureComplete = '0';
        $motivationComplete       = '0';
        $tribeometerComplete      = '0';
        $diagnosticsComplete      = '0';
        $feedbackComplete         = '0';

        if (! empty($date)) {
            // --- Use Service methods directly ---

            // Dot Report
            $dotStatus = $this->reportService->individualUserEngageDotReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($dotStatus) {
                $dotComplete = '100';
            }


            // Happy Index
            $happyIndexStatus = $this->reportService->individualUserEngageHappyIndexReport([
                'orgId'               => $orgId,
                'userId'              => $userId,
                'month'               => '',
                'HI_include_saturday' => $HI_include_saturday,
                'HI_include_sunday'   => $HI_include_sunday,
            ], $date);
            if ($happyIndexStatus) {
                $happyIndexComplete = $happyIndexStatus;
            }

            // Team Role
            $teamRoleStatus = $this->reportService->individualUserEngageTeamRoleReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($teamRoleStatus) {
                $teamRoleComplete = '100';
            }

            // Personality Type
            $personalityTypeStatus = $this->reportService->individualUserEngagePersonalityTypeReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($personalityTypeStatus) {
                $personalityTypeComplete = '100';
            }

            // Culture Structure
            $cultureStructureStatus = $this->reportService->individualUserEngageCultureStructureReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($cultureStructureStatus) {
                $cultureStructureComplete = '100';
            }

            // Motivation
            $motivationStatus = $this->reportService->individualUserEngageMotivationReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($motivationStatus) {
                $motivationComplete = '100';
            }

            // Tribeometer
            $tribeometerStatus = $this->reportService->individualUserEngageTribeometerReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($tribeometerStatus) {
                $tribeometerComplete = '100';
            }

            // Diagnostic
            $diagnosticStatus = $this->reportService->individualUserEngageDiagnosticReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if ($diagnosticStatus) {
                $diagnosticsComplete = '100';
            }

            // Feedback
            $feedbackStatus = $this->reportService->individualUserEngageFeedbackReport([
                'orgId'  => $orgId,
                'userId' => $userId,
                'month'  => '',
            ], $date);
            if (! empty($feedbackStatus)) {
                $feedbackComplete = $feedbackStatus;
            }

        }

        $engagementIndex = $dotComplete + $thumbsupComplete + $happyIndexComplete + $teamRoleComplete +
            $personalityTypeComplete + $cultureStructureComplete + $motivationComplete + $tribeometerComplete +
            $diagnosticsComplete + $feedbackComplete;

        return ($engagementIndex < 0) ? 0 : str_replace(',', '', number_format($engagementIndex, 2));
    }
 
}
