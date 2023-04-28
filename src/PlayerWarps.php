<?php

namespace XeonCh\PlayerWarpsUI;

use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\world\{World, Position};

use XeonCh\PlayerWarpsUI\Form\Form;
use XeonCh\PlayerWarpsUI\Form\SimpleForm;
use XeonCh\PlayerWarpsUI\Form\CustomForm;

use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use cooldogedev\BedrockEconomy\libs\cooldogedev\libSQL\context\ClosureContext;

/**
 * Summary of PlayerWarps
 */
class PlayerWarps extends PluginBase implements Listener
{

    public $dt;
    public $error = "§l§cERROR§r ";

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->saveResource("data.yml");
        $this->dt = new Config($this->getDataFolder() . "data.yml", Config::YAML, array());
    }

    /**
     * Summary of onCommand
     * @param CommandSender $sender
     * @param Command $cmd
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool
    {
        if ($cmd->getName() !== "pwarp")
            return true;
        if (!($sender instanceof Player)) {
            $sender->sendMessage("§cYou must be in-game to run this command");
            return true;
        }
        if (!isset($args[0])) {
            $sender->sendMessage("§l§cUsage: §r§a/pwarp <help>");
            return true;
        }

        $createPrice = (int) $this->getConfig()->getNested("price.create");
        $newPosPrice = $this->getConfig()->getNested("price.newpos");
        $prefix = $this->getConfig()->get("Prefix");
        $name = $sender->getName();

        switch ($args[0]) {
            case "create":
            case "set":
            case "add":
                if (!$sender->hasPermission("playerwarpsui.pwarp.create")) {
                    $sender->sendMessage($this->getConfig()->get("No-Permission"));
                    return true;
                }
                if (!isset($args[1])) {
                    $sender->sendMessage("§l§cUsage: §r§a/pwarp {$args[0]} <nameWarp>");
                    return true;
                }
                $pwarp = $args[1];
                if ($this->dt->exists($pwarp)) {
                    $sender->sendMessage($this->error . "§fThere is already a Player Warp with the name §e{$pwarp}");
                    return true;
                }
                BedrockEconomyAPI::legacy()->getPlayerBalance(
                    $sender->getName(),
                    ClosureContext::create(
                        function (?int $playerMoney) use ($sender, $createPrice, $prefix, $pwarp): void {
                            if ($playerMoney < $createPrice) {
                                $sender->sendMessage($prefix . "§cYou don't have enough money to create a Player Warp! You need §a" . $createPrice . "§c to create!");
                                return;
                            }

                            BedrockEconomyAPI::legacy()->subtractFromPlayerBalance(
                                $sender->getName(),
                                $createPrice,
                                ClosureContext::create(
                                    function (bool $wasUpdated) use ($sender, $createPrice, $prefix, $pwarp): void {
                                                if ($wasUpdated) {
                                                    $x = intval($sender->getPosition()->getX());
                                                    $y = intval($sender->getPosition()->getY());
                                                    $z = intval($sender->getPosition()->getZ());
                                                    $world = $sender->getWorld()->getDisplayName();
                                                    $owner = $sender->getName();
                                                    $this->dt->setNested("{$pwarp}.owner", $owner);
                                                    $this->dt->setNested("{$pwarp}.x", $x);
                                                    $this->dt->setNested("{$pwarp}.y", $y);
                                                    $this->dt->setNested("{$pwarp}.z", $z);
                                                    $this->dt->setNested("{$pwarp}.world", $world);
                                                    $this->dt->save();
                                                    $this->dt->reload();
                                                    $sender->sendMessage($prefix . "§fPlayer Warp §e{$pwarp}§f Succees Created!");
                                                    $sender->sendMessage("§c- {$createPrice}");
                                                }
                                            },
                                )
                            );
                        },
                    )
                );
                break;
            case "delete":
            case "del";
            case "remove";
                if (!$sender instanceof Player) {
                    return true;
                }
                if (!$sender->hasPermission("playerwarpsui.pwarp.delete")) {
                    $sender->sendMessage($this->getConfig()->get("No-Permission"));
                    return true;
                }
                if (!isset($args[1])) {
                    $sender->sendMessage("§l§cUsage: §r§a/pwarp {$args[0]} <nameWarp>");
                    return true;
                }
                $pwarp = $args[1];
                if (!$this->dt->exists($pwarp)) {
                    $sender->sendMessage($this->error . "§fThere is no PWarp with the name §e" . $pwarp . "");
                    return true;
                }
                if ($this->dt->getNested($pwarp . ".owner") != $name) {
                    $sender->sendMessage($this->error . "§fYou can't delete this pwarp, because you not owner this pwarp!");
                    return true;
                }
                $this->dt->remove($pwarp);
                $this->dt->save();
                $this->dt->reload();
                $sender->sendMessage($prefix . "§fThe Player Warp §e{$pwarp}§f successfull deleted!");
                break;
            case "newpos":
            case "new":
            case "edit":
                if (!$sender instanceof Player) {
                    return true;
                }
                if (!$sender->hasPermission("playerwarpsui.pwarp.newpos")) {
                    $sender->sendMessage($this->getConfig()->get("No-Permission"));
                    return true;
                }
                if (!isset($args[1])) {
                    $sender->sendMessage("§l§cUsage: §r§a/pwarp {$args[0]} <nameWarp>");
                    return true;
                }
                $pwarp = $args[1];
                if (!$this->dt->exists($pwarp)) {
                    $sender->sendMessage($this->error . "§fThere is no PWarp with the name §a" . $pwarp . "");
                    return true;
                }
                if ($this->dt->getNested($pwarp . ".owner") != $name) {
                    $sender->sendMessage($this->error . "§fYou can't cant edit this pwarp, because you not owner this warp");
                    return true;
                }
                BedrockEconomyAPI::legacy()->getPlayerBalance(
                    $sender->getName(),
                    ClosureContext::create(
                        function (?int $playerMoney) use ($sender, $newPosPrice, $prefix, $pwarp): void {
                            if ($playerMoney < $newPosPrice) {
                                $sender->sendMessage($prefix . "§cYou don't have enough money to edit a Player Warp! You need §a" . $newPosPrice . "§c to edit!");
                                return;
                            }
                            BedrockEconomyAPI::legacy()->subtractFromPlayerBalance(
                                $sender->getName(),
                                $newPosPrice,
                                ClosureContext::create(
                                    function (bool $wasUpdated) use ($sender, $newPosPrice, $prefix, $pwarp): void {
                                                if ($wasUpdated) {
                                                    $x = intval($sender->getPosition()->getX());
                                                    $y = intval($sender->getPosition()->getY());
                                                    $z = intval($sender->getPosition()->getZ());
                                                    $world = $sender->getWorld()->getDisplayName();
                                                    $this->dt->setNested("{$pwarp}.x", $x);
                                                    $this->dt->setNested("{$pwarp}.y", $y);
                                                    $this->dt->setNested("{$pwarp}.z", $z);
                                                    $this->dt->setNested("{$pwarp}.world", $world);
                                                    $this->dt->save();
                                                    $this->dt->reload();
                                                    $sender->sendMessage($prefix . "§fPlayer Warp §e{$pwarp}§f new position has been set!");
                                                    $sender->sendMessage("§c- {$newPosPrice}");
                                                }
                                            },
                                )
                            );
                        },
                    )
                );
                break;
            case "info":
                if (!$sender instanceof Player) {
                    return true;
                }
                if (!$sender->hasPermission("playerwarpsui.pwarp.info")) {
                    $sender->sendMessage($this->getConfig()->get("No-Permission"));
                    return true;
                }
                if (!isset($args[1])) {
                    $sender->sendMessage("§l§cUsage: §r§a/pwarp <info> <nameWarp>");
                    return true;
                }
                $pwarp = $args[1];
                if (!$this->dt->exists($pwarp)) {
                    $sender->sendMessage($this->error . "§fThere is no PWarp with the name §a" . $pwarp . "");
                    return true;
                }
                $pwarp = $args[1];
                $x = $this->dt->getNested("{$pwarp}.x");
                $y = $this->dt->getNested("{$pwarp}.y");
                $z = $this->dt->getNested("{$pwarp}.z");
                $world = $this->dt->getNested("{$pwarp}.world");
                $owner = $this->dt->getNested("{$pwarp}.owner");
                $sender->sendMessage("-----§bPwarps Info§r-----");
                $sender->sendMessage("Name: §e{$pwarp}");
                $sender->sendMessage("Owner: §e{$owner}");
                $sender->sendMessage("Position: (§eX: {$x}, Y: {$y}, Z: {$z}§f)");
                $sender->sendMessage("World: §e{$world}");
                $sender->sendMessage("-------------------------");
                break;
            case "help":
                if (!$sender instanceof Player) {
                    return true;
                }
                $sender->sendMessage("-----§bPWARP HELP§r-----");
                $sender->sendMessage("§f/pwarp <set,create,add> <warpName>§7 - §fCreate Player Warp");
                $sender->sendMessage("§f/pwarp <delete,del,remove> <warpName>§7 - §fDelete Player Warp");
                $sender->sendMessage("§f/pwarp <newpos,new,edit> <warpName>§7 - §fEdit new posotion Player Warp");
                $sender->sendMessage("§f/pwarp <info> <warpMame>§7 - §fShow information player warp");
                $sender->sendMessage("§f/pwarp menu§7 - §fOpen Player Warp Menu");
                $sender->sendMessage("§f/pwarp help§7 - §fPwarp help command");
                $sender->sendMessage("-------------------------");
                break;
            case "menu":
                $this->pwarpMenu($sender);
                break;
        }
        return true;
    }

    /**
     * Summary of pwarpMenu
     * @param Player $sender
     * @return void
     */
    public function pwarpMenu(Player $sender)
    {

        $form = new SimpleForm(function (Player $sender, $data) {
            if ($data == null) {
                return true;
            }
            $a = "§";
            $name = $sender->getName();
            $x = $this->dt->getAll()[$data]["x"];
            $y = $this->dt->getAll()[$data]["y"];
            $z = $this->dt->getAll()[$data]["z"];
            $world = $this->dt->getAll()[$data]["world"];
            $owner = $this->dt->getAll()[$data]["owner"];
            $msg = str_replace(
                ["{PLAYER}", "{x}", "{y}", "{z}", "{world}", "{owner}", "{pwarp}", "&"],
                [$name, $x, $y, $z, $world, $owner, $data, $a],
                $this->getConfig()->get("Tp-Message")
            );
            $sender->sendMessage($msg);
            $this->getServer()->getWorldManager()->loadWorld($world);
            $sender->teleport(new Position(floatval($x), floatval($y), floatval($z), $this->getServer()->getWorldManager()->getWorldByName($world)));
            return true;
        });
        $form->setTitle($this->getConfig()->get("menu")["title"]);
        $form->setContent($this->getConfig()->get("menu")["content"]);

        foreach ($this->dt->getAll() as $warp => $dataWarp) {
            $owner = $dataWarp["owner"];
            $form->addButton("§b{$warp}\n§f{$owner}", 0, "textures/ui/FriendsIcon", $warp);
        }

        $form->sendToPlayer($sender);
    }
}