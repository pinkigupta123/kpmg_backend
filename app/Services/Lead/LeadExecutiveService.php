<?php

namespace App\Services\Lead;

use App\Repositories\Lead\Interfaces\LeadUserRepositoryInterface;
use App\Models\LeadUser;
use Illuminate\Support\Collection;

class LeadExecutiveService
{
    protected LeadUserRepositoryInterface $userRepository;

    public function __construct(LeadUserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function list(array $filters = []): Collection
    {
        return $this->userRepository->all($filters);
    }

    public function find(int $id): ?LeadUser
    {
        return $this->userRepository->find($id);
    }

    public function create(array $data): array
    {
        $employeeId  = $this->generateNextEmployeeId();
        $tempPassword = !empty($data['password']) ? $data['password'] : 'KPMG#' . rand(1000, 9999);

        $user = $this->userRepository->create([
            'employee_id'      => $employeeId,
            'name'             => $data['name'],
            'mobile'           => $data['mobile'],
            'email'            => $data['email'] ?? null,
            'password'         => $tempPassword,
            'role'             => 'sales_executive',
            'branch'           => $data['branch'],
            'designation'      => $data['designation'] ?? 'Sales Executive',
            'permissions'      => array_values(array_unique($data['permissions'] ?? [])),
            'status'           => 'active',
            'is_temp_password' => true,
            'temp_password'    => $tempPassword,
        ]);

        return [
            'user'              => $user,
            'temporary_password' => $tempPassword,
        ];
    }

    public function update(int $id, array $data): ?LeadUser
    {
        $updateData = array_filter([
            'name'        => $data['name'] ?? null,
            'email'       => $data['email'] ?? null,
            'branch'      => $data['branch'] ?? null,
            'designation' => $data['designation'] ?? null,
            'status'      => $data['status'] ?? null,
        ]);

        // array_filter above would drop an empty list, so permissions are handled explicitly
        if (array_key_exists('permissions', $data)) {
            $updateData['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        }

        if (!empty($data['password'])) {
            $updateData['password']         = $data['password'];
            $updateData['temp_password']    = $data['password'];
            $updateData['is_temp_password'] = true;
        }

        return $this->userRepository->update($id, $updateData);
    }

    public function delete(int $id): bool
    {
        return $this->userRepository->delete($id);
    }

    public function resetPassword(int $id): array
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new \InvalidArgumentException('Executive not found.');
        }

        $newTempPassword = 'KPMG#' . rand(1000, 9999);

        $this->userRepository->update($id, [
            'password'         => $newTempPassword,
            'is_temp_password' => true,
            'temp_password'    => $newTempPassword,
        ]);

        return [
            'employee_id' => $user->employee_id,
            'username'    => $user->mobile,
            'password'    => $newTempPassword,
        ];
    }

    protected function generateNextEmployeeId(): string
    {
        $latestId = $this->userRepository->getLatestEmployeeId();

        if (!$latestId) {
            return 'EMP0002';
        }

        $numPart = preg_replace('/[^0-9]/', '', $latestId);
        $nextNum = intval($numPart) + 1;

        return 'EMP' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
}
