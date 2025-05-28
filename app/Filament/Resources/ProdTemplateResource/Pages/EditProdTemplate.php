<?php

namespace App\Filament\Resources\ProdTemplateResource\Pages;

use App\Filament\Resources\ProdTemplateResource;
use App\Models\ProdTemplate\ProdTemplateStep;
use App\Services\ProductService;
use Exception;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditProdTemplate extends EditRecord
{
    protected static string $resource = ProdTemplateResource::class;

    /**
     * @throws Exception
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->isDirty('product_id')) {
            try {
                /** @var ProdTemplateStep $step */
                foreach ($this->record->steps as $step) {
                    $outputProduct = app(ProductService::class)->createOrGetSemiFinished(
                        $this->record,
                        $step->work_station_id,
                        $step->is_last
                    );

                    $step->update(['output_product_id' => $outputProduct?->id]);
                }
            } catch (Throwable $e) {
                throw new Exception('Error in update ProdTemplate: ' . $e->getMessage());
            }
        }

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
