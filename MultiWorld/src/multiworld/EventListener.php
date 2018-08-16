<?php

declare(strict_types=1);

namespace multiworld;

use multiworld\api\WorldGameRulesAPI;
use multiworld\command\MultiWorldCommand;
use multiworld\util\LanguageManager;
use pocketmine\entity\Effect;
use pocketmine\entity\Living;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;

/**
 * Class EventListener
 * @package multiworld
 */
class EventListener implements Listener {

    /** @var MultiWorld $plugin */
    public $plugin;

    /** @var MultiWorldCommand $cmd */
    private $mwCommand;

    /** @var Item[][][] $inventories */
    private $inventories = [];

    /**
     * EventListener constructor.
     *
     * @param MultiWorld $plugin
     * @param MultiWorldCommand $mwCommand
     */
    public function __construct(MultiWorld $plugin, MultiWorldCommand $mwCommand) {
        $this->plugin = $plugin;
        $this->mwCommand = $mwCommand;
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onJoin(PlayerJoinEvent $event) {
        WorldGameRulesAPI::updateGameRules($event->getPlayer());
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onLevelChange(EntityLevelChangeEvent $event) {
        $entity = $event->getEntity();
        if($entity instanceof Player) {
            WorldGameRulesAPI::updateGameRules($entity);
        }
    }

    /**
     * @param EntityDeathEvent $event
     */
    public function onEntityDeath(EntityDeathEvent $event) {
        $entity = $event->getEntity();
        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($entity->getLevel());
        if(isset($levelGameRules["doMobLoot"]) && !$levelGameRules["doMobLoot"] && !$entity instanceof Player) {
            $event->setDrops([]);
        }
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onPlayerDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($player->getLevel());
        if(isset($levelGameRules["keepInventory"]) && !$levelGameRules["keepInventory"]) {
            $this->inventories[$player->getName()] = [$player->getInventory()->getContents(), $player->getArmorInventory()->getContents(), $player->getCursorInventory()->getContents()];
            $event->setDrops([]);
        }
    }

    /**
     * @param PlayerRespawnEvent $event
     */
    public function onPlayerRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($player->getLevel());
        if(isset($levelGameRules["keepInventory"]) && !$levelGameRules["keepInventory"] && isset($this->inventories[$player->getName()])) {
            $player->getInventory()->setContents(array_shift($this->inventories[$player->getName()]));
            $player->getArmorInventory()->setContents(array_shift($this->inventories[$player->getName()]));
            $player->getCursorInventory()->setContents(array_shift($this->inventories[$player->getName()]));
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($player->getLevel());
        if(isset($levelGameRules["doTileDrops"]) && !$levelGameRules["doTileDrops"]) {
            $event->setDrops([]);
        }
    }

    /**
     * @param EntityRegainHealthEvent $event
     */
    public function onRegenerate(EntityRegainHealthEvent $event) {
        $entity = $event->getEntity();
        if(!$entity instanceof Living) return;
        if($entity->hasEffect(Effect::REGENERATION)) return;

        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($entity->getLevel());
        if(isset($levelGameRules["naturalRegeneration"]) && !$levelGameRules["naturalRegeneration"]) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event) {
        $entity = $event->getEntity();

        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($entity->getLevel());
        if(isset($levelGameRules["pvp"]) && !$levelGameRules["pvp"]) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param EntityExplodeEvent $event
     */
    public function onExplode(EntityExplodeEvent $event) {
        $entity = $event->getEntity();

        $levelGameRules = WorldGameRulesAPI::getLevelGameRules($entity->getLevel());
        if(isset($levelGameRules["tntexplodes"]) && !$levelGameRules["tntexplodes"]) {
            $event->setCancelled(true);
        }
    }



    /**
     * @param DataPacketReceiveEvent $event
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event) {
        $packet = $event->getPacket();
        if($packet instanceof LoginPacket) {
            LanguageManager::$players[$packet->username] = $packet->locale;
        }
    }
}