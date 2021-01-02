<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\VideoController;
use App\Models\Category;
use App\Models\Genre;
use App\Models\Video;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Tests\Exceptions\TestException;
use Tests\TestCase;
use Tests\Traits\TestSaves;
use Tests\Traits\TestValidations;

class VideoControllerTest extends TestCase
{
    use DatabaseMigrations, TestValidations, TestSaves;

    private $video;
    private $sendData;

    protected function setUp(): void
    {
        parent::setup();
        $this->video = factory(Video::class)->create([
            'opened' => false
        ]);
        $this->sendData = [
            'title' => 'title',
            'description' => 'description',
            'year_launched' => 1992,
            'rating' => Video::RATING_LIST[0],
            'duration' => 90,
        ];
    }

    public function testIndex()
    {
        $response = $this->get(route('videos.index'));
        $response->assertStatus(200)
            ->assertJson([$this->video->toArray()]);
    }

    public function testInvalidationRequired()
    {
        $data = [
            'title' => '',
            'description' => '',
            'year_launched' => '',
            'rating' => '',
            'duration' => '',
            'categories_id' => '',
            'genres_id' => '',
        ];
        $this->assertInvalidationStoreAction($data, 'required');
        $this->assertInvalidationUpdateAction($data, 'required');
    }

    public function testInvalidationMax()
    {
        $data = ['title' => str_repeat('a', 256)];
        $this->assertInvalidationStoreAction($data, 'max.string', ['max' => 255]);
        $this->assertInvalidationUpdateAction($data, 'max.string', ['max' => 255]);
    }

    public function testInvalidationInteger()
    {
        $data = [
            'duration' => 's'
        ];
        $this->assertInvalidationStoreAction($data, 'integer');
        $this->assertInvalidationUpdateAction($data, 'integer');
    }

    public function testInvalidationYearLaunchedField()
    {
        $data = [
            'year_launched' => 'a'
        ];
        $this->assertInvalidationStoreAction($data, 'date_format', ['format' => 'Y']);
        $this->assertInvalidationUpdateAction($data, 'date_format', ['format' => 'Y']);
    }

    public function testIvalidationOpenedField()
    {
        $data = [
            'opened' => 's'
        ];
        $this->assertInvalidationStoreAction($data, 'boolean');
        $this->assertInvalidationUpdateAction($data, 'boolean');
    }

    public function testInvalidationRatingField()
    {
        $data = [
            'rating' => 0
        ];
        $this->assertInvalidationStoreAction($data, 'in');
        $this->assertInvalidationUpdateAction($data, 'in');
    }

    public function testInvalidationCategoriesIdField()
    {
        $data = [
            'categories_id' => 'a',
        ];
        $this->assertInvalidationStoreAction($data, 'array');
        $this->assertInvalidationUpdateAction($data, 'array');

        $data = [
            'categories_id' => [100],
        ];
        $this->assertInvalidationStoreAction($data, 'exists');
        $this->assertInvalidationUpdateAction($data, 'exists');

        $category = factory(Category::class)->create();
        $category->delete();
        $data = [
            'categories_id' => [$category->id],
        ];
        $this->assertInvalidationStoreAction($data, 'exists');
        $this->assertInvalidationUpdateAction($data, 'exists');
    }

    public function testInvalidationGenresIdField()
    {
        $data = [
            'genres_id' => 'a'
        ];
        $this->assertInvalidationStoreAction($data, 'array');
        $this->assertInvalidationUpdateAction($data, 'array');

        $data = [
            'genres_id' => [100],
        ];
        $this->assertInvalidationStoreAction($data, 'exists');
        $this->assertInvalidationUpdateAction($data, 'exists');
        $genre = factory(Genre::class)->create();
        $genre->delete();
        $data = [
            'genres_id' => [$genre->id],
        ];
        $this->assertInvalidationStoreAction($data, 'exists');
        $this->assertInvalidationUpdateAction($data, 'exists');
    }

    public function testSave()
    {
        $category = factory(Category::class)->create();
        $genre = factory(Genre::class)->create();

        $data = [
            [
                'send_data' => $this->sendData + [
                        'genres_id' => [$genre->id],
                        'categories_id' => [$category->id],
                    ],
                'test_data' => $this->sendData + ['opened' => false,]
            ],
            [
                'send_data' => $this->sendData + [
                        'genres_id' => [$genre->id],
                        'categories_id' => [$category->id],
                        'opened' => true
                    ],
                'test_data' => $this->sendData + ['opened' => true]
            ],
            [
                'send_data' => $this->sendData + [
                        'rating' => Video::RATING_LIST[1],
                        'genres_id' => [$genre->id],
                        'categories_id' => [$category->id],
                    ],
                'test_data' => $this->sendData + ['rating' => Video::RATING_LIST[1]]
            ],
        ];

        foreach ($data as $key => $value) {
            $response = $this->assertStore(
                $value['send_data'],
                $value['test_data'] + ['deleted_at' => null]
            );
            $response->assertJsonStructure([
                'created_at',
                'updated_at'
            ]);

            $response = $this->assertUpdate(
                $value['send_data'],
                $value['test_data'] + ['deleted_at' => null]
            );
            $response->assertJsonStructure([
                'created_at',
                'updated_at'
            ]);
        }
    }

    public function testRollBackStore()
    {
        /** @var  $controller */
        $controller = \Mockery::mock(VideoController::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $controller
            ->shouldReceive('validate')
            ->withAnyArgs()
            ->andReturn($this->sendData);

        $controller
            ->shouldReceive('rulesStore')
            ->withAnyArgs()
            ->andReturn([]);

        $request = \Mockery::mock(Request::class);

        $controller->shouldReceive('handleRelations')
            ->once()
            ->andThrow(new TestException());

        try {
            $controller->store($request);
        } catch (TestException $exception) {
            $this->assertCount(1, Video::all());
        }
    }

    public function testShow()
    {
        $response = $this->get(route('videos.show', ['video' => $this->video->id]));
        $response
            ->assertStatus(200)
            ->assertJson($this->video->toArray());
    }

    public function testDestroy()
    {
        $response = $this->json('DELETE', route('videos.destroy', ['video' => $this->video->id]));
        $response->assertStatus(204);
        $this->assertNull(Video::find($this->video->id));
        $this->assertNotNull(Video::withTrashed()->find($this->video->id));
    }

    protected function routeStore()
    {
        return route('videos.store');
    }

    protected function routeUpdate()
    {
        return route('videos.update', ['video' => $this->video->id]);
    }

    protected function model()
    {
        return Video::class;
    }
}
