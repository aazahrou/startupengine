<?php

namespace App;

use App\Traits\IsApiResource;
use Illuminate\Database\Eloquent\Model;
use \Conner\Tagging\Taggable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Fico7489\Laravel\EloquentJoin\Traits\EloquentJoin;
use App\Traits\RelationshipsTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use \Altek\Accountant\Recordable as Recordable;

class Product extends Model implements \Altek\Accountant\Contracts\Recordable
{

    use \Altek\Accountant\Recordable;

    use RelationshipsTrait;

    use EloquentJoin;

    use Taggable;

    use SoftDeletes;

    use IsApiResource;

    protected $useTableAlias = false;
    protected $appendRelationsCount = false;
    protected $leftJoin = false;
    protected $aggregateMethod = 'MAX';

    protected $guarded = [];
    protected $fillable = ['name', 'slug', 'json', 'status'];

    public function json()
    {
        if($this->json != null) {
            $json = json_decode($this->json);
        }
        else {
            $json = null;
        }
        return $json;

    }

    public function transformations(){

        $allowed = [];
        if($this->status == 'ACTIVE'){
            $allowed[] = 'subscribe';
        }

        $results = [];
        foreach($allowed  as $function){
            $results[$function] = $this->$function('schema');
        }
        return $results;
    }

    public function details(){
        \Stripe\Stripe::setApiKey(stripeKey('secret'));
        if($this->stripe_id == null) {

            if($this->name == null) {
                $this->name = 'Product '.rand(11);
                $this->save();
            }
            $object = \Stripe\Product::create(['type' => 'service', 'name' => $this->name]);
            $this->stripe_id = $object->id;
            $this->save();
        }
        else {
            try {
                $object = \Stripe\Product::retrieve($this->stripe_id);
            } catch (\Exception $e) {
            }
        }
        if (!isset($object)) { $object = null; }
            return $object;
    }

    public function stripePlans(){
        $product_id = ($this->stripe_id);
        \Stripe\Stripe::setApiKey(stripeKey('secret'));
        $plans= \Stripe\Plan::all(["product" => $product_id ]);
        return $plans;
    }

    public function subscribe($input = null){
        if($input == 'schema'){
            $plans = $this->stripePlans()->data;
            $options = [];
            if($plans != null){

                foreach($plans as $plan){
                    $item =[];
                    $item['value'] = $plan->id;
                    $item['label'] = $plan->nickname;
                    $amount = "$".$plan->amount/100 ." ". strtoupper($plan->currency);
                    $item['description'] = $amount. " / " . ucwords($plan->interval);
                    $options[$plan->id] = $item;

                }
            }
            $schema = [
                'label' => 'Add Subscription',
                'slug' => 'Subscribe',
                'description' => 'You may subscribe to this product.',
                'instruction' => 'Select a plan.',
                'confirmation_message' => null,
                'options' => $options,
                'success_message' => "Subscription successfully created.",
                'requirements' => [
                    'permissions_any' => [
                        'change own subscription',
                        'change others subscription']
                ]
            ];
            return $schema;
        }
        else{
            //Do Something
            $userId = app('request')->input('user_id');
            $planId = app('request')->input('action');
            if($userId != null) {
                $user = \App\User::find($userId);
                //dump($user);
                if($user != null) {
                    \Stripe\Stripe::setApiKey(stripeKey('secret'));

                    $subscription = $this->details();
                    $newStripeSubscription = \Stripe\Subscription::create([
                        "customer" => $user->stripe_id,
                        "items" => [
                            [
                                "plan" => $planId
                            ],
                        ]
                    ]);
                    Artisan::queue("command:SyncStripeSubscriptions");

                }

            }
            return $this;
        }
    }


    public function searchFields() {
        return ['slug', 'name', 'description', 'json->sections->about->fields->type', 'json->sections->about->fields->description'];
    }

    public function thumbnail(){
        if($this->schema() != null && $this->schema()->sections != null){
            foreach($this->schema()->sections as $section){
                if($section->fields != null){
                    foreach ($section->fields as $field => $value) {

                        if(isset($value->isThumbnail) && $value->isThumbnail == true) {
                            $slug = $section->slug;
                            $string = "sections->".$slug."->fields->".$field;
                            //dd($this->content()->sections->$slug->fields->$field);
                            if($this->content() != null && $this->content()->sections != null && $this->content()->sections->$slug != null && isset($this->content()->sections->$slug->fields) && isset($this->content()->sections->$slug->fields->$field)) {
                                return $this->content()->sections->$slug->fields->$field;
                            }
                            else { return null; }

                        }
                    }
                }
            }
        }

    }

    public function content()
    {
        $json = $this->json;

        if (gettype($json) == 'string') {
            $json = json_decode($json, true);
        }
        if (gettype($json) == 'object' OR gettype($json) == 'array') {

            $json = json_decode(json_encode($json));

        }
        return $json;
    }

    public function schema()
    {
        $path = file_get_contents(storage_path().'/schemas/product.json');
        $schema = json_decode($path);
        return $schema;
    }

    public function plans(){
        $plans = \App\Plan::where('product_id', '=', $this->id)->orderBy('price', 'asc')->get();
        foreach($plans as $plan){
            $plan->schema = $plan->schema();
        }
        return $plans;
    }

    public function purchases(){
        $request = request();
        if($request->input('startDate') != null){
            $startDate = \Carbon\Carbon::parse($request->input('startDate'));
        }
        else {
            $startDate = new Carbon();
            $startDate = $startDate->subDays(30);
        }
        if($request->input('endDate') != null){
            $endDate = \Carbon\Carbon::parse($request->input('endDate'));
        }
        else {
            $endDate = new Carbon();
        }
        $purchases = $this->hasMany('App\AnalyticEvent', 'model_id')->where('event_type', '=', 'product purchased')->where('created_at', '>=', $startDate)->where('created_at', '<=', $endDate);
        //dd($purchases->get());
        return $purchases;
    }

    public function syncFromStripe(){
        $compressed = $this->details()->metadata['se_json'];
        if($compressed){
            $this->json = $compressed;
            $this->save();
        }
        //$uncompressed = gzdecode($compressed);
        //dd($uncompressed);
    }

    public function postSave($execute = false){
        //$compressed = gzencode($item->json, 9);
        //dd(gzdecode($compressed));
        //dd(strlen($item->json) - strlen(gzcompress($item->json, 9)));
        if($execute == true){
            $stripeProduct = $this->details();
            $stripeProduct->metadata['se_json'] = $this->json;
            $stripeProduct->save();
            return true;
        }
        else {
            return true;
        }
    }

}