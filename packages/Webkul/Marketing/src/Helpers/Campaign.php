<?php

namespace Webkul\Marketing\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Webkul\Marketing\Repositories\EventRepository;
use Webkul\Marketing\Repositories\CampaignRepository;
use Webkul\Marketing\Repositories\TemplateRepository;
use Webkul\Marketing\Mail\NewsletterMail;

class Campaign
{
    /**
     * EventRepository object
     *
     * @var \Webkul\Marketing\Repositories\EventRepository
     */
    protected $eventRepository;

    /**
     * CampaignRepository object
     *
     * @var \Webkul\Marketing\Repositories\CampaignRepository
     */
    protected $campaignRepository;

    /**
     * TemplateRepository object
     *
     * @var \Webkul\Marketing\Repositories\TemplateRepository
     */
    protected $templateRepository;

    /**
     * Create a new helper instance.
     *
     * @param  \Webkul\Marketing\Repositories\EventRepository  $eventRepository
     * @param  \Webkul\Marketing\Repositories\CampaignRepository  $campaignRepository
     * @param  \Webkul\Marketing\Repositories\TemplateRepository  $templateRepository
     *
     * @return void
     */
    public function __construct(
        EventRepository $eventRepository,
        CampaignRepository $campaignRepository,
        CampaignRepository $templateRepository
    )
    {
        $this->eventRepository = $eventRepository;

        $this->campaignRepository = $campaignRepository;

        $this->templateRepository = $templateRepository;
    }

    /**
     * @return void
     */
    public function process(): void
    {
        $campaigns = $this->campaignRepository->getModel()
            ->leftJoin('marketing_events', 'marketing_campaigns.marketing_event_id', 'marketing_events.id')
            ->select('marketing_campaigns.*')
            ->where('status', 1)
            ->where(function ($query) {
                $query->where('marketing_events.date', Carbon::now()->format('Y-m-d'))
                    ->orWhereNull('marketing_events.date');
            })
            ->get();

        foreach ($campaigns as $campaign) {
            if ($campaign->event->name == 'Birthday') {
                $emails = $this->getBirthdayEmails($campaign);
            } else {
                $emails = $this->getEmailAddresses($campaign);
            }

            foreach ($emails as $email) {
                Mail::queue(new NewsletterMail($email, $campaign));
            }
        }
    }

    /**
     * Build the message.
     *
     * @param  \Webkul\Marketing\Contracts\Campaign  $campaign
     * @return array
     */
    public function getEmailAddresses($campaign)
    {
        $newsletterEmails = app('\Webkul\Core\Repositories\SubscribersListRepository')->getModel()
            ->where('is_subscribed', 1)
            ->where('channel_id', $campaign->channel_id)
            ->get('email');

        $customerGroupEmails = $campaign->customer_group->customers()->where('subscribed_to_news_letter', 1)->get('email');

        $emails = [];

        foreach ($newsletterEmails as $row) {
            $emails[] = $row->email;
        }

        foreach ($customerGroupEmails as $row) {
            $emails[] = $row->email;
        }

        return array_unique($emails);
    }

    /**
     * Return customer's emails who has a birthday today
     *
     * @param  \Webkul\Marketing\Contracts\Campaign  $campaign
     * @return array
     */
    public function getBirthdayEmails($campaign)
    {
        $customerGroupEmails = $campaign->customer_group->customers()
            ->whereRaw('DATE_FORMAT(date_of_birth, "%m-%d") = ?', [Carbon::now()->format('m-d')])
            ->where('subscribed_to_news_letter', 1)
            ->get('email');

        $emails = [];

        foreach ($customerGroupEmails as $row) {
            $emails[] = $row->email;
        }

        return $emails;
    }
}