<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Recipe extends Model
{
    use HasFactory;

    protected $fillable = [
        "name", "image", "number_of_servings", "cooking_time", "how_to_cook", "category_id", "user_id"
    ];

    protected $casts = [
        "how_to_cook" => "array"
    ];

    public static $message = [
        "show" => "Receita recuperada",
        "created" => "Receita salva",
        "index" => "Receitas recuperadas",
        "image" => "Imagem da receita salva"
    ];

    public static function message($text) {
        return self::$message[$text];
    }

    public function getMatchedIngredientsAttribute() {
        return $this->attributes['matched_ingredients'] ?? [];
    }

    public function setMatchedIngredientsAttribute($value) {
        if(!isset($this->attributes['matched_ingredients']))
            $this->attributes['matched_ingredients'] = [];

        array_push($this->attributes['matched_ingredients'], $value);
    }

    public function ingredients() {
        return $this->belongsToMany(Ingredient::class)->withPivot("quantity","measure");
    }
    
    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function saveImage($folder, $file) {
        // FIREBASE UPLOAD
        $storage = app('firebase.storage');
        $bucket = $storage->getBucket();
        $object = $bucket->object($folder.$file);
        if($object->exists()) {
            $object->copy($bucket, [
                "name" => "public/recipes/".$file,
                "predefinedAcl" => "publicRead"
            ]);

            $this->attributes['image'] = env('STORAGE_URL') . "/public%2Frecipes%2F{$file}?alt=media";
            $this->save();

            $object->delete();
        }
    }

    public function saveIngredients($list_of_ingredients) {
        foreach($list_of_ingredients as $ingredient_array) {
            $ingredient = Ingredient::firstOrCreate([
                "name" => strtolower($ingredient_array["name"])
            ]);
            $this->ingredients()->attach($ingredient->id, [
                "quantity" => $ingredient_array["quantity"],
                "measure" => strtolower($ingredient_array["measure"])
            ]);
        }
    }

    public function hasIngredient($ingredientName) {
        return $this->ingredients()->get()->contains(function ($ingredient) use ($ingredientName) {
            if(empty($ingredientName)) return false;
            return is_int(strpos($ingredient->name, strtolower($ingredientName)));
        });
    }

    private function numberOfMatchedIngredients($ingredients) {
        $numberOfIngredients = 0;
        foreach($ingredients as $ingredientName) {
            if($this->hasIngredient($ingredientName)){
                $numberOfIngredients++;
                $this->matched_ingredients = $ingredientName;
            }
        }
        return $numberOfIngredients;
    }

    public function scopeSearchRecipes($query) {
        return $query->select("recipes.id", "recipes.name", "recipes.image", "recipes.created_at", "recipes.updated_at", "recipes.category_id", "recipes.user_id");
    }

    public function scopeMinTime($query, $value) {
        if($value > 0) 
            return $query->where('recipes.cooking_time','>=', $value);
        
        return $query;
    }

    public function scopeMaxTime($query, $value) {
        if($value > 0) 
            return $query->where('recipes.cooking_time','<=', $value);
        
        return $query;
    }

    public function scopeCategory($query, $value) {
        if($value > 0) 
            return $query->where('recipes.category_id', $value);
        
        return $query;
    }

    public function scopeWithIngredients($query, $ingredients = []) {
        $data = $query->with('ingredients')->get()->sortByDesc(function ($recipe) use ($ingredients) { 
            return $recipe->numberOfMatchedIngredients($ingredients);
        });
        
        if(!empty($ingredients)) {
            $recipes = $data->filter(function ($recipe) {
                return isset($recipe->attributes["matched_ingredients"]);
            });
            
            if($recipes->isEmpty()) 
                throw new NotFoundHttpException("Não existe receitas com esses ingredientes");

            return $recipes;
        }

        return $data;
    }
}
