<?php

declare(strict_types=1);

final class Delivery
{
    public function __construct(private Store $store)
    {
    }

    public function attempt(string $id): void
    {
        $claim = $this->store->claim($id);
        if (!$claim) {
            return;
        }

        try {
            $priority = $claim['priority'];
            if ($claim['classified_at'] === null) {
                [$priority, $source] = classifyComment($claim['comment']);
                $this->store->classify($claim, $priority, $source);
            }

            $crm = new Bitrix();
            $value = $crm->priorityValues[$priority];
            $uuid = $claim['request_uuid'];
            $leadId = $claim['crm_lead_id'] === null ? $crm->findLead($uuid) : (string) $claim['crm_lead_id'];
            if (!$this->store->ownsClaim($claim)) {
                return;
            }
            $leadId ??= $crm->addLead($claim, $value);
            $this->store->rememberLead($claim, $leadId);

            if (!$crm->hasPriority($leadId, $uuid, $value)) {
                if (!$this->store->ownsClaim($claim)) {
                    return;
                }
                $crm->setPriority($leadId, $value);
                if (!$crm->hasPriority($leadId, $uuid, $value)) {
                    throw new IntegrationFailure('crm_priority_mismatch', 'Приоритет в карточке не совпал с приоритетом заявки.');
                }
            }
        } catch (IntegrationFailure $failure) {
            $this->store->finish($claim, $failure);
            return;
        }
        $this->store->finish($claim, null);
    }
}
