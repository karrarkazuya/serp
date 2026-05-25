from odoo import models, fields, exceptions
from zk import ZK, const
from datetime import datetime
from pytz import timezone

class fingerprint_lograw(models.Model):
    _name = 'jtemployees.fd.lograw'
    _description = 'Fingerprint Log'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    user_id                = fields.Integer(string='User ID')
    timestamp              = fields.Datetime(string='Timestamp')
    device                 = fields.Char(string='Device')