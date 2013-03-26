<?php
App::uses('Level', 'Model');
class LevelsControllerTest extends ControllerTestCase {
  public $fixtures = array('app.level', 'app.user');
  public $autoRender = false;

  // configures a mock as fixture user 'bob'
  function mockAsBob($mockLevel = false) {
    // mock Auth component
    $options = array(
        'components' => array('Auth' => array('user', 'loggedIn'))
    );

    // mock Level#save, as well
    if($mockLevel) {
      $options['models'] = array('Level' => array('save'));
    }

    $Levels = $this->generate('Levels', $options);

    $Levels->Auth
      ->staticExpects($this->any())
      ->method('user')
      ->will($this->returnValue(2));

    $Levels->Auth
      ->expects($this->any())
      ->method('loggedIn')
      ->will($this->returnValue(true));

    return $Levels;
  }

  public function setUp() {
    parent::setUp();
    $this->Level = ClassRegistry::init('Level');
  }

  public function testIndex() {
    $result = $this->testAction('/levels/index/', array('return' => 'vars'));
    $this->assertEqual($result['levels'], $this->Level->find('all'));
    $this->assertGreaterThan(0, sizeof($result['levels']));
  }

  public function testEditNoId() {
    $this->setExpectedException('BadRequestException');
    $this->testAction('/levels/edit/', array('return' => 'vars'));
  }

  public function testEditBadId() {
    $this->setExpectedException('BadRequestException');
    $this->testAction('/levels/edit/-1', array('return' => 'vars'));
  }

  public function testEditNotLoggedIn() {
    $Levels = $this->generate('Levels', array(
      'components' => array(
        'Auth' => array(
          'loggedIn'
        )
      )
    ));

    $Levels
      ->expects($this->any())
      ->method('loggedIn')
      ->will($this->returnValue(false));


    $this->setExpectedException('ForbiddenException');
    $this->testAction('/levels/edit/1', array('return' => 'vars'));
  }

  public function testEditLevelOwnedByOtherUser() {
    $this->mockAsBob();

    $level = $this->Level->findByUserId(1);
    $this->setExpectedException('ForbiddenException');
    $this->testAction('/levels/edit/' . $level['Level']['id']);
  }

  public function testEditSuccess() {
    $this->mockAsBob();
    $level = $this->Level->findByUserId(2);

    $levelData = array(
      'name' => 'level' . time(),
      'content' => 'empty (more or less)',
      'levelgen' => '',
      'description' => 'descriptive'
    );

    $result = $this->testAction('/levels/edit/' . $level['Level']['id'], array(
      'data' => $levelData,
      'method' => 'post',
      'return' => 'vars'
    ));

    $this->assertStringEndsWith('/levels/view/' . $level['Level']['id'], $this->headers['Location']);
  }

  public function testEditFailure() {
    $Levels = $this->mockAsBob(true);

    $Levels->Level
      ->expects($this->once())
      ->method('save')
      ->will($this->returnValue(false));

    $level = $this->Level->findByUserId(2);

    $levelData = array(
      'name' => 'level' . time(),
      'content' => 'empty (more or less)',
      'levelgen' => '',
      'description' => 'descriptive'
    );

    $result = $this->testAction('/levels/edit/' . $level['Level']['id'], array(
      'data' => $levelData,
      'method' => 'post',
      'return' => 'vars'
    ));

    // should not redirect
    $this->assertArrayNotHasKey('Location', $this->headers);
    // should not update level
    $resultLevel = $Levels->Level->findById($level['Level']['id']);
    $this->assertNotEquals($levelData, $resultLevel['Level']);
  }

  public function testEditWithoutData() {
    $this->mockAsBob();

    $level = $this->Level->findById(3);
    $result = $this->testAction('/levels/edit/' . $level['Level']['id'], array('return' => 'view'));
    // no redirect
    $this->assertArrayNotHasKey('Location', $this->headers);
    // puts level data into the form
    $this->assertRegexp("/uploaded by bob/", $result);
  }

  public function testView() {
    $this->mockAsBob();
    $level = $this->Level->findByUserId(2);
    $result = $this->testAction('/levels/view/' . $level['Level']['id'], array(
      'return' => 'vars'
    ));
    $this->assertEquals($level, $result['level']);
  }
}