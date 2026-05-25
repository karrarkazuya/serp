from odoo import models, fields, exceptions

class JTEmployeesCertificates(models.Model):
    _name = 'jtemployees.certificates'
    _description = 'certificates for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Selection(
        [
            ('graduate', 'Graduate'),
            ('bachelor', 'Bachelor'),
            ('master', 'Master'),
            ('doctor', 'Doctor'),
            ('other', 'Other'),
        ],
        string='Name',
        default='other',
        required=True,
        tracking=True
    )
    amount = fields.Float(
        string='Amount',
        required=True,
        tracking=True
    )

