<?php

namespace App\Gam;

require(__DIR__."/../../vendor/autoload.php");

use DateTime;
use DateTimeZone;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\AdManagerSession;
use Google\AdsApi\AdManager\AdManagerSessionBuilder;
use Google\AdsApi\AdManager\Util\v201808\AdManagerDateTimes;
use Google\AdsApi\AdManager\v201808\AdUnitTargeting;
use Google\AdsApi\AdManager\v201808\CostType;
use Google\AdsApi\AdManager\v201808\CreativePlaceholder;
use Google\AdsApi\AdManager\v201808\CreativeRotationType;
use Google\AdsApi\AdManager\v201808\CustomCriteria;
use Google\AdsApi\AdManager\v201808\CustomCriteriaComparisonOperator;
use Google\AdsApi\AdManager\v201808\CustomCriteriaSet;
use Google\AdsApi\AdManager\v201808\CustomCriteriaSetLogicalOperator;
use Google\AdsApi\AdManager\v201808\Goal;
use Google\AdsApi\AdManager\v201808\GoalType;
use Google\AdsApi\AdManager\v201808\InventoryTargeting;
use Google\AdsApi\AdManager\v201808\LineItem;
use Google\AdsApi\AdManager\v201808\LineItemService;
use Google\AdsApi\AdManager\v201808\LineItemType;
use Google\AdsApi\AdManager\v201808\Money;
use Google\AdsApi\AdManager\v201808\NetworkService;
use Google\AdsApi\AdManager\v201808\Size;
use Google\AdsApi\AdManager\v201808\StartDateTimeType;
use Google\AdsApi\AdManager\v201808\Targeting;
use Google\AdsApi\AdManager\v201808\UnitType;
use Google\AdsApi\AdManager\Util\v201808\StatementBuilder;


class LineItemManager extends GamManager
{
	protected $orderId;
    protected $sizes;
    protected $ssp;
    protected $currency;
    protected $keyId;
    protected $valueId;
    protected $bucket;
    protected $lineItem;
    protected $lineItemName;
	protected $namePrefix;
	protected $keyValueIds;

	public function setOrderId($orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function setSizes($sizes)
    {
        $this->sizes = $sizes;
        return $this;
    }

    public function setSsp($ssp)
    {
        $this->ssp = $ssp;
        return $this;
    }

    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    public function setKeyId($keyId)
    {
        $this->keyId = $keyId;
        return $this;
    }

    public function setValueId($valueId)
    {
        $this->valueId = $valueId;
        return $this;
    }

    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        return $this;
    }

    public function setRootAdUnitId($rootAdUnitId)
    {
        $this->rootAdUnitId = $rootAdUnitId;
        return $this;
    }

    public function setLineItemName()
    {
        if (empty($this->ssp)){
            $this->lineItemName = $this->namePrefix."_".$this->bucket;
        } else {
            $this->lineItemName = ucfirst($this->ssp)."_".$this->namePrefix."_".$this->bucket;
        }
        return $this;
    }

    public function getLineItemName()
    {
        return $this->lineItemName;;
    }

	public function setNamePrefix($namePrefix)
	{
		$this->namePrefix = $namePrefix;
		return $this;
	}

	public function setKeyValueIds($keyValueIds)
	{
		$this->keyValueIds = $keyValueIds;
		return $this;
	}

    public function setUpLineItem($update)
    {    
        $lineItem = $this->getLineItem();
        if(empty($lineItem))
        {
            return $this->createLineItem();
        }
        else
        {
            return $this->updateLineItem($lineItem);
        }
    }

    public function getAllLineItems()
	{
		$output = [];
		$lineItemService = $this->gamServices->get($this->session, LineItemService::class);

		$statementBuilder = (new StatementBuilder())->orderBy('id ASC');
		$data = $lineItemService->getLineItemsByStatement($statementBuilder->toStatement());
		if($data->getResults() == null)
		{
			return $output;
		}
		foreach ($data->getResults() as $lineItem) {
		    array_push($output, $lineItem);
		}
		return $output;
	}

    public function getLineItem()
    {
        $output = "";
        $lineItemService = $this->gamServices->get($this->session, LineItemService::class);
        $statementBuilder = (new StatementBuilder())
            ->orderBy('id ASC')
            ->where('name = :name AND orderId = :orderId')
            ->WithBindVariableValue('name', $this->lineItemName)
            ->WithBindVariableValue('orderId', $this->orderId);
        $data = $lineItemService->getLineItemsByStatement($statementBuilder->toStatement());
        if ($data->getResults() !== null)
        {
            foreach ($data->getResults() as $lineItem) {
                $output = $lineItem;
            }
        }
        return $output;
    }

    public function getAllOrderLineItems()
    {
        $output = array();

        $lineItemService = $this->gamServices->get($this->session, LineItemService::class);
        $statementBuilder = (new StatementBuilder())
            ->orderBy('id ASC')
            ->where('orderId = :orderId')
            ->WithBindVariableValue('orderId', $this->orderId);
        $data = $lineItemService->getLineItemsByStatement($statementBuilder->toStatement());
		if($data->getResults() == null)
		{
			return $output;
		}
		foreach ($data->getResults() as $lineItem) {
			$output[$lineItem->getName()] = array(
                "lineItemId"=>$lineItem->getId(),
                "lineItemName"=>$lineItem->getName()
            );
    	}

        return $output;
    }

	public function createLineItem()
	{
		$output = [];
		$lineItemService = $this->gamServices->get($this->session, LineItemService::class);
        
        $results = $lineItemService->createLineItems([$this->setUpHeaderBiddingLineItem()
            ->setStartDateTimeType(StartDateTimeType::IMMEDIATELY)
            ->setUnlimitedEndDateTime(true)
        ]);

        foreach ($results as $i => $lineItem) {
            $foo = array(
                "lineItemId"=>$lineItem->getId(),
                "lineItemName"=>$lineItem->getName()
            );
            array_push($output, $foo);
        }
        return $output[0];
	}

    public function updateLineItem($lineItem)
    {
        $output = [];

        $lineItemService = $this->gamServices->get($this->session, LineItemService::class);
        $results = $lineItemService->updateLineItems([$this->setUpHeaderBiddingLineItem()
            ->setId($lineItem->getId())
            ->setStartDateTime($lineItem->getStartDateTime())
            ->setUnlimitedEndDateTime(true)
        ]);
        
        foreach ($results as $i => $lineItem) {
            $foo = array(
                "lineItemId"=>$lineItem->getId(),
                "lineItemName"=>$lineItem->getName()
            );
            array_push($output, $foo);
        }
        return $output[0];
    }


	private function setUpHeaderBiddingLineItem()
	{

		$lineItem = new LineItem();
        $lineItem->setName($this->lineItemName);
        $lineItem->setOrderId($this->orderId);

        $targeting = new Targeting();


        // Create inventory targeting.
        $inventoryTargeting = new InventoryTargeting();
        $adUnitTargeting = new AdUnitTargeting();
        $adUnitTargeting->setAdUnitId($this->rootAdUnitId);
        $adUnitTargeting->setIncludeDescendants(true);

        $inventoryTargeting->setTargetedAdUnits([$adUnitTargeting]);
		
        $targeting->setInventoryTargeting($inventoryTargeting);

        // Create Key/Values Targeting

        $customCriteria = new CustomCriteria();
        $customCriteria->setKeyId($this->keyId);
        $customCriteria->setOperator(CustomCriteriaComparisonOperator::IS);
        $customCriteria->setValueIds([$this->valueId]);

        $customCriteriaList = [];
        array_push($customCriteriaList, $customCriteria);
        foreach ($this->keyValueIds as $kv) {
            $customCriteria = new CustomCriteria();
            $customCriteria->setKeyId($kv['keyId']);

			if ($kv["operator"] == "is") {
				$customCriteria->setOperator(CustomCriteriaComparisonOperator::IS);
			} else {
				$customCriteria->setOperator(CustomCriteriaComparisonOperator::IS_NOT);
			}
    
            $customCriteria->setValueIds([$kv['valueId']]);

            array_push($customCriteriaList, $customCriteria);
        }

        $subCustomCriteriaSet = new CustomCriteriaSet();
        $subCustomCriteriaSet->setLogicalOperator(CustomCriteriaSetLogicalOperator::AND_VALUE);
        $subCustomCriteriaSet->setChildren($customCriteriaList);
              
		$topCustomCriteriaSet = new CustomCriteriaSet();
        $topCustomCriteriaSet->setLogicalOperator(
            CustomCriteriaSetLogicalOperator::OR_VALUE
        );
		$topCustomCriteriaSet->setChildren(
            [$subCustomCriteriaSet]
        );
        $targeting->setCustomTargeting($topCustomCriteriaSet);

        $lineItem->setTargeting($targeting);

        // Allow the line item to be booked even if there is not enough inventory.
        $lineItem->setAllowOverbook(true);

        // Set the line item type to STANDARD and priority to High. In this case,
        // 8 would be Normal, and 10 would be Low.
        $lineItem->setLineItemType(LineItemType::PRICE_PRIORITY);
        $lineItem->setPriority(12);

        // Set the creative rotation type to even.
        $lineItem->setCreativeRotationType(CreativeRotationType::EVEN);
        

        // Set the size of creatives that can be associated with this line item.
        $lineItem->setCreativePlaceholders($this->setCreativePlaceholders());
        
        

        // Set the length of the line item to run.
        //$lineItem->setStartDateTimeType(StartDateTimeType::IMMEDIATELY);
        //$lineItem->setUnlimitedEndDateTime(true);

                // Set the cost per unit to $2.
        $lineItem->setCostType(CostType::CPM);
        $lineItem->setCostPerUnit(new Money($this->currency, floatval($this->bucket)*1000000));

        $goal = new Goal();
        $goal->setGoalType(GoalType::NONE);
        $lineItem->setPrimaryGoal($goal);

        return $lineItem;
	}


    private function setCreativePlaceholders()
    {
        $output = []; 
        foreach ($this->sizes as $element) {
            $size = new Size();
            $size->setWidth($element[0]);
            $size->setHeight($element[1]);
            $size->setIsAspectRatio(false);

            // Create the creative placeholder.
            $creativePlaceholder = new CreativePlaceholder();
            $creativePlaceholder->setSize($size);
            array_push($output, $creativePlaceholder);
        }
        return $output;
    } 
}