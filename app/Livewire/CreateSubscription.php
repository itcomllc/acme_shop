<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\SSLSaaSService;
use Illuminate\Support\Facades\Auth;

class CreateSubscription extends Component
{
    public string $selectedPlan = '';
    public array $domains = [''];
    public string $cardNonce = '';
    public bool $loading = false;
    public string $error = '';
    public array $plans = [];

    protected $rules = [
        'selectedPlan' => 'required|in:basic,professional,enterprise',
        'domains' => 'required|array|min:1|max:100',
        'domains.*' => 'required|string|regex:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
        'cardNonce' => 'required|string'
    ];

    public function mount(array $plans): void
    {
        $this->plans = $plans;
    }

    public function addDomain(): void
    {
        $selectedPlanData = $this->getSelectedPlanDataProperty();
        
        if ($selectedPlanData && count($this->domains) < $selectedPlanData['max_domains']) {
            $this->domains[] = '';
        }
    }

    public function removeDomain(int $index): void
    {
        if (count($this->domains) > 1) {
            unset($this->domains[$index]);
            $this->domains = array_values($this->domains);
        }
    }

    public function createSubscription(SSLSaaSService $sslService): void
    {
        $this->validate();
        
        $this->loading = true;
        $this->error = '';
        
        try {
            $validDomains = array_filter($this->domains, fn($d) => trim($d) !== '');
            
            // SSLSaaSServiceの正しい引数順序
            $result = $sslService->createSubscription(
                Auth::user(),
                $this->selectedPlan,
                $validDomains,
                $this->cardNonce  // 4番目の引数として追加
            );

            if ($result['success']) {
                $this->dispatch('subscriptionCreated');
                session()->flash('message', 'Subscription created successfully!');
                
                // リダイレクト（必要に応じて）
                //return redirect()->route('dashboard');
            } else {
                $this->error = $result['error'] ?? 'Failed to create subscription';
            }
            
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Square Web Payments SDKからのカードnonce受信
     */
    public function setCardNonce(string $nonce): void
    {
        $this->cardNonce = $nonce;
    }

    /**
     * 選択されたプランの詳細を取得
     */
    public function getSelectedPlanDataProperty(): ?array
    {
        return $this->plans[$this->selectedPlan] ?? null;
    }

    /**
     * 現在のドメイン数が制限内かチェック
     */
    public function canAddDomain(): bool
    {
        $planData = $this->getSelectedPlanDataProperty();
        if (!$planData || !isset($planData['max_domains'])) {
            return false;
        }
        
        return count($this->domains) < $planData['max_domains'];
    }

    /**
     * プランのドメイン制限数を取得
     */
    public function getMaxDomainsForPlan(): int
    {
        $planData = $this->getSelectedPlanDataProperty();
        return $planData['max_domains'] ?? 1;
    }

    /**
     * 残りドメイン数を取得
     */
    public function getRemainingDomains(): int
    {
        $maxDomains = $this->getMaxDomainsForPlan();
        return max(0, $maxDomains - count($this->domains));
    }

    /**
     * プラン変更時のドメイン調整
     */
    public function updatedSelectedPlan(): void
    {
        $maxDomains = $this->getMaxDomainsForPlan();
        
        // 現在のドメイン数がプラン制限を超えている場合は調整
        if (count($this->domains) > $maxDomains) {
            $this->domains = array_slice($this->domains, 0, $maxDomains);
        }
        
        // 空のドメインスロットがない場合は1つ追加（制限内であれば）
        if (count($this->domains) < $maxDomains && !in_array('', $this->domains)) {
            $this->domains[] = '';
        }
    }

    public function render()
    {
        return view('livewire.create-subscription');
    }
}