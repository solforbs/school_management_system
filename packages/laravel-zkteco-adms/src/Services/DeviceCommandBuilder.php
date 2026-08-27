<?php

declare(strict_types=1);

namespace Athwari\LaravelZktecoAdms\Services;

use DateTimeInterface;

/**
 * Fluent API for building and queuing device commands.
 */
class DeviceCommandBuilder
{
    public function __construct(
        private readonly CommandManager $commandManager,
    ) {}

    /**
     * Send an INFO command to request device information.
     */
    public function getInfo(string $serialNumber): int
    {
        return $this->commandManager->sendInfoCommand($serialNumber);
    }

    /**
     * Send a CHECK (heartbeat) command.
     */
    public function checkConnection(string $serialNumber): int
    {
        return $this->commandManager->sendCheckCommand($serialNumber);
    }

    /**
     * Send a REBOOT command to restart the device.
     */
    public function reboot(string $serialNumber): int
    {
        return $this->commandManager->sendRebootCommand($serialNumber);
    }

    /**
     * Set the device's clock to a given moment.
     *
     * The wall clock the terminal ends up showing is the one the passed
     * time carries, so hand it a time already in the timezone the device
     * should display.
     */
    public function setTime(string $serialNumber, DateTimeInterface $time): int
    {
        return $this->commandManager->sendSetTimeCommand($serialNumber, $time);
    }

    /**
     * Send a CLEAR LOG command to clear attendance logs on device.
     */
    public function clearLogs(string $serialNumber): int
    {
        return $this->commandManager->sendClearLogsCommand($serialNumber);
    }

    /**
     * Send a CLEAR DATA command to clear all data on device.
     */
    public function clearData(string $serialNumber): int
    {
        return $this->commandManager->sendClearDataCommand($serialNumber);
    }

    /**
     * Send a DATA QUERY USERINFO command to query all users.
     */
    public function queryUsers(string $serialNumber): int
    {
        return $this->commandManager->sendQueryUsersCommand($serialNumber);
    }

    /**
     * Send a DATA UPDATE USERINFO command to add/update a user.
     */
    public function addUser(string $serialNumber, string $pin, string $name, int $privilege = 0, string $card = ''): int
    {
        return $this->commandManager->sendUserAddCommand($serialNumber, $pin, $name, $privilege, $card);
    }

    /**
     * Send a DATA DELETE USERINFO command to delete a user.
     */
    public function deleteUser(string $serialNumber, string $pin): int
    {
        return $this->commandManager->sendUserDeleteCommand($serialNumber, $pin);
    }

    /**
     * Queue a raw command.
     */
    public function rawCommand(string $serialNumber, string $command): int
    {
        return $this->commandManager->queueCommand($serialNumber, $command);
    }
}
