THEMES=learn2

install:
	bin/grav install
	bin/gpm install $(THEMES)
	@make clear

update:
	bin/grav composer
	bin/gpm selfupgrade
	bin/gpm update
	@make clear

clear:
	bin/grav clear-cache --all
