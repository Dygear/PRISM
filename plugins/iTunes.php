<?php
class iTunes extends Plugins
{
	const URL = 'http://lfsforum.net/forumdisplay.php?f=312';
	const NAME = 'iTunes';
	const AUTHOR = 'Mark \'Dygear\' Tomlin';
	const VERSION = PHPInSimMod::VERSION;
	const DESCRIPTION = 'In Game iTunes HUD';

	private $isShowing = FALSE;
	private $isPlaying = FALSE;
	private $buttons = array();

	public function __construct()
	{
		$this->iTunes = new COM("iTunes.Application") OR $this->iTunes = FALSE;
		$this->registerSayCommand('iTunes', 'iTunes');

		// Buttons
		# Controls
		$this->buttons['PT'] = new Button(0, 'PreviousTrack', 'iTunes');
		$this->buttons['PT']->BStyle(ISB_DARK)->T(128)->L(  0)->W( 16)->H(  4)->Text('<<')->registerOnClick($this, 'onPrevTrack');
		$this->buttons['PP'] = new Button(0, 'PlayPause', 'iTunes');
		$this->buttons['PP']->BStyle(ISB_DARK)->T(128)->L( 16)->W( 16)->H(  4)->Text('|>')->registerOnClick($this, 'onPlayPause');
		$this->buttons['NT'] = new Button(0, 'NextTrack', 'iTunes');
		$this->buttons['NT']->BStyle(ISB_DARK)->T(128)->L( 32)->W( 16)->H(  4)->Text('>>')->registerOnClick($this, 'onNextTrack');
		# Song Info
		$this->buttons['TM'] = new Button(0, 'Time', 'iTunes');
		$this->buttons['TM']->BStyle(ISB_DARK)->T(128)->L( 48)->W( 16)->H(  4)->registerOnClick($this, 'onNextTrack');
		$this->buttons['cA'] = new Button(0, 'currentArtist', 'iTunes');
		$this->buttons['cA']->BStyle(ISB_DARK)->T(132)->L(  0)->W( 64)->H(  4);
		$this->buttons['cB'] = new Button(0, 'currentAlbum', 'iTunes');
		$this->buttons['cB']->BStyle(ISB_DARK)->T(136)->L(  0)->W( 64)->H(  4);
		$this->buttons['cS'] = new Button(0, 'currentSong', 'iTunes');
		$this->buttons['cS']->BStyle(ISB_DARK)->T(140)->L(  0)->W( 64)->H(  4);
		
	}

	public function iTunes()
	{
		$this->isShowing = !$this->isShowing;
		if ($this->isShowing === TRUE)
		{
			ButtonManager::removeButtonsByGroup(0, 'iTunes');
		}

		$this->buttons['PT']->send();
		$this->buttons['PP']->send();
		$this->buttons['NT']->send();
		$this->buttons['TM']->send();
		$this->onScreenRefresh();

		$this->createTimer('onScreenRefresh', 1, Timer::REPEAT);
	}

	public function onScreenRefresh()
	{
		if ($this->isShowing === FALSE)
			return;
	
		$currentTrack =& $this->iTunes->CurrentTrack();
		$this->buttons['TM']->Text(date('i:s', $this->iTunes->PlayerPosition) . ' / ' . date('i:s', $this->iTunes->CurrentTrack()->Duration))->send();
		$this->buttons['cA']->Text($currentTrack->Artist)->send();
		$this->buttons['cB']->Text($currentTrack->Album)->send();
		$this->buttons['cS']->Text($currentTrack->Name)->send();
	}
	
	public function onPlayPause()
	{
		$this->iTunes->PlayPause();
		$currentTrack =& $this->iTunes->CurrentTrack();
		$this->buttons['TM']->Text(date('i:s', $this->iTunes->PlayerPosition) . ' / ' . date('i:s', $this->iTunes->CurrentTrack()->Duration))->send();
		$this->buttons['cA']->Text($currentTrack->Artist)->send();
		$this->buttons['cB']->Text($currentTrack->Album)->send();
		$this->buttons['cS']->Text($currentTrack->Name)->send();
	}
	public function onNextTrack()
	{
		$this->iTunes->NextTrack();
		$currentTrack =& $this->iTunes->CurrentTrack();
		$this->buttons['TM']->Text(date('i:s', $this->iTunes->PlayerPosition) . ' / ' . date('i:s', $this->iTunes->CurrentTrack()->Duration))->send();
		$this->buttons['cA']->Text($currentTrack->Artist)->send();
		$this->buttons['cB']->Text($currentTrack->Album)->send();
		$this->buttons['cS']->Text($currentTrack->Name)->send();
	}
	public function onPrevTrack()
	{
		$this->iTunes->PreviousTrack();
		$this->buttons['TM']->Text(date('i:s', $this->iTunes->PlayerPosition) . ' / ' . date('i:s', $this->iTunes->CurrentTrack()->Duration))->send();
		$this->buttons['cA']->Text($currentTrack->Artist)->send();
		$this->buttons['cB']->Text($currentTrack->Album)->send();
		$this->buttons['cS']->Text($currentTrack->Name)->send();
	}
	
	public function __deconstruct()
	{
		$this->iTunes = NULL;
		ButtonManager::removeButtonsByGroup(0, 'iTunes');
	}
}
?>