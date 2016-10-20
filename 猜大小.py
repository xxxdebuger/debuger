 #coding=utf-8
 

 
import os
import sys
reload(sys)
sys.setdefaultencoding('gb2312')

from random import randint

print "welcome"
again = 1
while(again > 0):

    g=input(u"请任意输入一个数\n")
    secret = randint(1, 100)
    while(g != secret):
        if(g > secret):
            print u"大了"
        else:
            print u"小了"
        g=input(u"再试一次\n")
    print u"猜对了\n"
    again = input(u"是否再玩一次？确认输入1，退出输入0\n")
print u"游戏结束"
